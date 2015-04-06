<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople;

// Contexts.
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Drupal\Propeople\User\UserContext;
use Behat\Drupal\Propeople\Email\EmailContext;
use Behat\Drupal\Propeople\Drush\DrushContext;
use Behat\Drupal\Propeople\Wysiwyg\WysiwygContext;
use Behat\Drupal\Propeople\Redirect\RedirectContext;
use Drupal\DrupalExtension\Context\MinkContext;
use Drupal\DrupalExtension\Context\DrupalContext;
use Drupal\DrupalExtension\Context\MessageContext;

// Exceptions.
use WebDriver\Exception\NoSuchElement;

// Helpers.
use Behat\Mink\Session;
use Behat\Mink\Element\NodeElement;
use Behat\Behat\Hook\Scope\StepScope;
use Behat\Mink\Element\DocumentElement;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;

/**
 * @see __call()
 *
 * @method UserContext getUserContext()
 * @method EmailContext getEmailContext()
 * @method DrushContext getDrushContext()
 * @method WysiwygContext getWysiwygContext()
 * @method RedirectContext getRedirectContext()
 * @method PropeopleContext getPropeopleContext()
 * @method MinkContext getMinkContext()
 * @method DrupalContext getDrupalContext()
 * @method MessageContext getMessageContext()
 */
class RawPropeopleContext extends RawDrupalContext implements SnippetAcceptingContext
{
    const DRUPAL_EXTENSION_CONTEXT_NAMESPACE = 'Drupal\DrupalExtension\Context';
    private $workingElement = null;
    private $baseUrl = '';

    /**
     * @param string $method
     * @param array $arguments
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        $context = explode('get', $method);
        $context = end($context);
        $namespace = explode('Context', $context);
        $namespace = reset($namespace);
        $environment = $this->getEnvironment();
        $object = '';

        foreach (array(
            array(__NAMESPACE__, $namespace),
            array(__NAMESPACE__),
            array('Drupal', 'DrupalExtension', 'Context'),
        ) as $class) {
            $class[] = $context;
            $class = implode('\\', $class);

            if ($environment->hasContextClass($class)) {
                $object = $class;
                break;
            }
        }

        if (empty($object)) {
            throw new \Exception(sprintf(
                'Method %s does not exist or "%s" context are not configured in "behat.yml"',
                $method,
                $object
            ));
        }

        return $environment->getContext($object);
    }

    /**
     * @param BeforeSuiteScope $suite
     *
     * @BeforeSuite @api
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

        foreach ($selectors as $type) {
            if ($type == 'region' && empty($regions[$locator])) {
                $type = 'css';
            }

            $element = $page->find($type, $locator);

            if ($element) {
                return $element;
            }
        }

        return $page->find('css', 'body');
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
     * @param StepScope $event
     *
     * @return int
     */
    public static function isStepImpliesJsEvent(StepScope $event)
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
        $this->userCreate($user);

        return $user;
    }

    public function isLoggedIn()
    {
        $session = $this->getSession();
        // We need to visit any page to start session, otherwise the next exception will
        // be thrown: "Unable to access the response content before visiting a page".
        $session->visit($this->locatePath('/'));

        if (!empty($session->getCookie(session_name()))) {
            return true;
        }

        $body = $session->getPage()->find('css', 'body');

        return $body && in_array('logged-in', explode(' ', $body->getAttribute('class')));
    }

    /**
     * Find all field labels by text.
     *
     * @param string $text
     *   Label text.
     * @param bool $first
     *   Indicates to find all or only first found item.
     * @param bool $return_fields
     *   if "true" - labels will be return, "false" - fields.
     *
     * @return NodeElement[]
     */
    public function findFieldLabels($text, $first = false, $return_fields = false)
    {
        $page = $this->getWorkingElement();
        $labels = $fields = array();

        /* @var NodeElement $label */
        foreach ($page->findAll('xpath', "//label[starts-with(text(), '$text')]") as $label) {
            $element_id = $label->getAttribute('for');

            if ($element_id) {
                $labels[] = $label;
            }

            // We trying to find an ID with "-upload" suffix, because some
            // image inputs in Drupal are suffixed by it.
            foreach (array($element_id, "$element_id-upload") as $element_id) {
                $field = $page->findById($element_id);

                if ($field) {
                    $fields[] = $field;
                    break;
                }
            }

            if ($first) {
                break;
            }
        }

        return $return_fields ? $fields : $labels;
    }

    /**
     * @param string $selector
     * @param NodeElement|DocumentElement $element
     *
     * @return NodeElement|null
     */
    public function findField($selector, $element = null)
    {
        $element = $element ?: $this->getWorkingElement();
        $field = $element->findField($selector);

        if (!$field) {
            $field = $this->findFieldLabels($selector, true, true);

            if (is_array($field)) {
                $field = reset($field);
            }
        }

        return $field;
    }

    public function waitAjaxAndAnimations()
    {
        $this->getSession()->wait(1000, "window.__behatAjax === false && !jQuery(':animated').length");
    }
}
