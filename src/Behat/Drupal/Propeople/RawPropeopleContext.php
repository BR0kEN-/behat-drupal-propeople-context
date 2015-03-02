<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople;

// Contexts.
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Drupal\Propeople\Email\EmailContext;
use Behat\Drupal\Propeople\Drush\DrushContext;
use Behat\Drupal\Propeople\Redirect\RedirectContext;

// Exceptions.
use WebDriver\Exception\NoSuchElement;

// Helpers.
use Behat\Mink\Session;
use Behat\Behat\Snippet\Snippet;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\DocumentElement;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;

class RawPropeopleContext extends RawDrupalContext implements SnippetAcceptingContext
{
    const DRUPAL_EXTENSION_CONTEXT_NAMESPACE = 'Drupal\DrupalExtension\Context';
    private $workingElement = null;
    private $baseUrl = '';

    /**
     * @param BeforeSuiteScope $suite
     *
     * @BeforeSuite
     */
    public static function beforeSuite(BeforeSuiteScope $suite)
    {
        self::setDrupalVariables(array(
          // Set to "FALSE", because the administration menu will not be rendered.
          // https://www.drupal.org/node/2023625#comment-8607207
          'admin_menu_cache_client' => false,
        ));
    }

    /**
     * Set the Drupal variables.
     *
     * @param array $variables
     *   An associative array where key is a variable name and a value - value.
     */
    public static function setDrupalVariables(array $variables)
    {
        foreach ($variables as $name => $value) {
            variable_set($name, $value);
        }
    }

    /**
     * @param string $selector
     *   Element selector.
     * @param mixed $element
     *   Existing element or null.
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
     * @throws \Behat\Behat\Context\Exception\ContextNotFoundException
     *
     * @return EmailContext
     */
    public function getEmailContext()
    {
        return $this->getEnvironment()->getContext(__NAMESPACE__ . '\Email\EmailContext');
    }

    /**
     * @throws \Behat\Behat\Context\Exception\ContextNotFoundException
     *
     * @return RedirectContext
     */
    public function getRedirectContext()
    {
        return $this->getEnvironment()->getContext(__NAMESPACE__ . '\Redirect\RedirectContext');
    }

    /**
     * @throws \Behat\Behat\Context\Exception\ContextNotFoundException
     *
     * @return DrushContext
     */
    public function getDrushContext()
    {
        return $this->getEnvironment()->getContext(__NAMESPACE__ . '\Drush\DrushContext');
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

    /**
     * @param string $url
     *
     * @return string
     */
    public function unTrailingSlashIt($url)
    {
        return trim($url, '/');
    }

    /**
     * Check JS events in step definition.
     *
     * @param Snippet $event
     *
     * @return int
     */
    public static function isStepImpliesJsEvent(Snippet $event)
    {
        return preg_match('/(follow|press|click|submit)/i', $event->getStep()->getText());
    }

    /**
     * @return \Drupal\Component\Utility\Random
     */
    public function getRandom()
    {
        return parent::getRandom();
    }

    /**
     * @return \Drupal\Driver\DrushDriver
     */
    public function getDrushDriver()
    {
        return $this->getDriver('drush');
    }

    /**
     * @param array $data
     *   Additional data for user account.
     *
     * @return \stdClass
     */
    public function createTestUser(array $data = array())
    {
        $random = $this->getRandom();
        $username = $random->name(8);
        $user = $data + array(
          'name' => $username,
          'pass' => $random->name(16),
          'mail' => "$username@example.com",
        );

        $user = (object) $user;
        $this->getDriver()->userCreate($user);

        return $user;
    }
}
