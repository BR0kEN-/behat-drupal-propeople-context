<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople;

// Contexts.
use Behat\Drupal\Propeople as BDP;
use Drupal\DrupalExtension\Context as DEC;
use Behat\Behat\Context\SnippetAcceptingContext;

// Exceptions.
use WebDriver\Exception\NoSuchElement;

// Helpers.
use Behat\Mink\Session;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\DocumentElement;
use Behat\Behat\Hook\Scope\StepScope;

/**
 * @see __call()
 *
 * @method BDP\User\UserContext getUserContext()
 * @method BDP\Form\FormContext getFormContext()
 * @method BDP\Email\EmailContext getEmailContext()
 * @method BDP\Drush\DrushContext getDrushContext()
 * @method BDP\Wysiwyg\WysiwygContext getWysiwygContext()
 * @method BDP\Redirect\RedirectContext getRedirectContext()
 * @method BDP\PropeopleContext getPropeopleContext()
 * @method DEC\MinkContext getMinkContext()
 * @method DEC\DrupalContext getDrupalContext()
 * @method DEC\MessageContext getMessageContext()
 * @method \Drupal\Component\Utility\Random getRandom()
 */
class RawPropeopleContext extends DEC\RawDrupalContext implements SnippetAcceptingContext
{
    private $workingElement = null;
    private $baseUrl = '';
    protected $tags = [];

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

        foreach ([
            [__NAMESPACE__, $namespace],
            [__NAMESPACE__],
            ['Drupal', 'DrupalExtension', 'Context'],
        ] as $class) {
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
        if (empty($this->baseUrl)) {
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
     * @return \Drupal\Driver\DrushDriver
     */
    public function getDrushDriver()
    {
        return $this->getDriver('drush');
    }

    /**
     * Wait for all AJAX requests and jQuery animations.
     */
    public function waitAjaxAndAnimations()
    {
        $this->getSession()
            ->wait(1000, "window.__behatAjax === false && !jQuery(':animated').length && !jQuery.active");
    }

    /**
     * @param string $tag
     *
     * @return bool
     *   Indicates the state of tag existence in a feature and/or scenario.
     */
    public function hasTag($tag)
    {
        return in_array($tag, $this->tags);
    }
}
