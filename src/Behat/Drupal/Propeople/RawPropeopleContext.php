<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople;

// Contexts.
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;

// Exceptions.
use WebDriver\Exception\NoSuchElement;

// Helpers.
use Behat\Mink\Session;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\DocumentElement;

class RawPropeopleContext extends RawDrupalContext implements SnippetAcceptingContext
{
    const DRUPAL_EXTENSION_CONTEXT_NAMESPACE = 'Drupal\DrupalExtension\Context';
    private $originalMailSystem = array('default-system' => 'DefaultMailSystem');
    private $workingElement = null;
    private $messages = array();
    private $baseUrl = '';

    /**
     * @BeforeScenario @emails
     */
    public function initializeEmailTesting()
    {
        // Store the original system to restore after the scenario.
        $this->originalMailSystem = variable_get('mail_system', $this->originalMailSystem);
        $this->setDrupalVariables(array(
          // Set the mail system for testing. It will store an emails in
          // "drupal_test_email_collector" Drupal variable instead of sending.
          'mail_system' => array('default-system' => 'TestingMailSystem'),
          // Set to "FALSE", because the administration menu will not be rendered.
          // https://www.drupal.org/node/2023625#comment-8607207
          'admin_menu_cache_client' => false,
        ));
    }

    /**
     * @AfterScenario @emails
     */
    public function restoreEmailSubmissionSettings()
    {
        $this->setDrupalVariables(array(
          // Bring back the original mail system.
          'mail_system' => $this->originalMailSystem,
          // Flush the email buffer, allowing us to reuse this step
          // definition to clear existing mail.
          'drupal_test_email_collector' => array(),
        ));
    }

    /**
     * @throws \RangeException
     *   When no one message was sent.
     *
     * @return array
     *   An array of messages that was sent.
     */
    public function getEmailMessages()
    {
        // We can't use variable_get() because $conf is only
        // fetched once per scenario.
        if (!$this->messages) {
            $this->messages = db_select('variable', 'v')
              ->fields('v', array('value'))
              ->condition('name', 'drupal_test_email_collector', '=')
              ->execute()
              ->fetchField();

            $this->messages = unserialize($this->messages);
        }

        if (!$this->messages) {
            throw new \RangeException('No one message was not sent.');
        }

        return $this->messages;
    }

    /**
     * Set the Drupal variables.
     *
     * @param array $variables
     *   An associative array where key is a variable name and a value - value.
     */
    public function setDrupalVariables(array $variables)
    {
        foreach ($variables as $name => $value) {
            variable_set($name, $value);
        }
    }

    /**
     * @param string $selector
     *
     * @throws NoSuchElement
     */
    public function throwNoSuchElementException($selector, $element)
    {
        if (!$element) {
            throw new NoSuchElement(sprintf('Cannot find an element by "%s" selector.', $selector));
        }
    }

    /**
     * @return \Behat\Behat\Context\Environment\InitializedContextEnvironment
     */
    public function getEnvironment()
    {
        return $this->getDrupal()->getEnvironment();
    }

    /**
     * @throws \Behat\Behat\Context\Exception\ContextNotFoundException
     *
     * @return \Drupal\DrupalExtension\Context\MinkContext
     */
    public function getMinkContext()
    {
        return $this->getEnvironment()->getContext(self::DRUPAL_EXTENSION_CONTEXT_NAMESPACE . '\MinkContext');
    }

    /**
     * @throws \Behat\Behat\Context\Exception\ContextNotFoundException
     *
     * @return \Drupal\DrupalExtension\Context\DrupalContext
     */
    public function getDrupalContext()
    {
        return $this->getEnvironment()->getContext(self::DRUPAL_EXTENSION_CONTEXT_NAMESPACE . '\DrupalContext');
    }

    /**
     * @throws \Behat\Behat\Context\Exception\ContextNotFoundException
     *
     * @return \Drupal\DrupalExtension\Context\MessageContext
     */
    public function getMessageContext()
    {
        return $this->getEnvironment()->getContext(self::DRUPAL_EXTENSION_CONTEXT_NAMESPACE . '\MessageContext');
    }

    /**
     * @throws \Behat\Behat\Context\Exception\ContextNotFoundException
     *
     * @return PropeopleContext
     */
    public function getPropeopleContext()
    {
        return $this->getEnvironment()->getContext(__NAMESPACE__ . '\PropeopleContext');
    }

    /**
     * Get selector by name.
     *
     * @param string $name
     *   Selector name from the configuration file.
     *
     * @return string
     *   CSS selector.
     *
     * @throws \Exception
     *   If selector does not exits.
     */
    public function getDrupalSelector($name)
    {
        $selectors = $this->getDrupalParameter('selectors');

        if (!isset($selectors[$name])) {
            throw new \Exception(sprintf('No such selector configured: %s', $name));
        }

        return $selectors[$name];
    }

    /**
     * @return string
     *   Clean base url without any suffixes.
     */
    public function getBaseUrl()
    {
        if (!$this->baseUrl) {
            $url = parse_url($this->getMinkParameter('base_url'));
            $this->baseUrl = $url['scheme'] . '://' . $url['host'];
        }

        return $this->baseUrl;
    }

    /**
     * @return string
     *   URL to files directory.
     */
    public function getFilesUrl()
    {
        return $this->getBaseUrl() . '/sites/default/files';
    }

    /**
     * Try to find an element by different selectors.
     *
     * In this method was not used the "$this->getMinkContext()->getRegion($locator);",
     * because it can throw an exception, when region was not defined. If this happens,
     * we'll try to use the $locator as CSS selector.
     *
     * @param array $selectors
     *   Selector types. E.g. "region", "css", "xpath" etc.
     * @param string $locator
     *   Locator for selector. Can be passed the region name or CSS selector.
     *
     * @return NodeElement|null
     */
    public function findElementBySelectors(array $selectors, $locator)
    {
        $page = $this->getWorkingElement();
        $regions = $this->getDrupalParameter('region_map');
        $element = null;

        foreach ($selectors as $type) {
            $css = $locator;

            if ($type == 'region' && empty($regions[$locator])) {
                $type = 'css';
                $css = $locator;
            }

            $element = $page->find($type, $css);

            if ($element) {
                break;
            }
        }

        return $element;
    }

    /**
     * @param Session $session
     *
     * @return DocumentElement|NodeElement
     */
    public function getWorkingElement(Session $session = null)
    {
        if ($this->workingElement) {
            return $this->workingElement;
        }

        $session = $session ?: $this->getSession();

        return $session->getPage();
    }

    /**
     * @param NodeElement $element
     */
    public function setWorkingElement(NodeElement $element)
    {
        $this->workingElement = $element;
    }

    public function unsetWorkingElement()
    {
        $this->workingElement = null;
    }

    public function unTrailingSlashIt($url)
    {
        return trim($url, '/');
    }
}
