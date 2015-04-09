<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople;

// Exceptions.
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

// Helpers.
use WebDriver\Service\CurlService;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

class PropeopleContext extends RawPropeopleContext
{
    /**
     * @param string $name
     *   An iframe name (null for switching back).
     *
     * @Given /^(?:|I )switch to an iframe "([^"]*)"$/
     * @Then /^(?:|I )switch back from an iframe$/
     */
    public function iSwitchToAnIframe($name = null)
    {
        $this->getSession()->switchToIFrame($name);
    }

    /**
     * Check that an image was uploaded and can be viewed on the page.
     *
     * @Then /^(?:|I )should see the thumbnail$/
     */
    public function iShouldSeeTheThumbnail()
    {
        $page = $this->getWorkingElement();
        $thumb = false;

        foreach (array('media-thumbnail', 'image-preview') as $classname) {
            if ($thumb) {
                break;
            }

            $thumb = $page->find('css', ".$classname img");
        }

        if (!$thumb) {
            throw new \Exception('An expected image tag was not found.');
        }

        $file = explode('?', $thumb->getAttribute('src'));
        $file = reset($file);

        $curl = new CurlService();
        list(, $info) = $curl->execute('GET', $file);

        if (empty($info) || strpos($info['content_type'], 'image/') === false) {
            throw new FileNotFoundException(sprintf('%s did not return an image', $file));
        }
    }

    /**
     * Check that the page have no error messages and fields - error classes.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Exception
     *
     * @Then /^(?:|I )should see no errors$/
     */
    public function iShouldSeeNoErrors()
    {
        $selector = $this->getDrupalSelector('error_message_selector');

        if (empty($selector)) {
            throw new \InvalidArgumentException('The "error_message_selector" in behat.yml is not configured.');
        }

        $page = $this->getSession()->getPage();
        $errors = $page->find('css', $this->getDrupalSelector('error_message_selector'));

        // Some modules are inserted an empty container for errors before
        // they are arise. The "Clientside Validation" - one of them.
        if ($errors) {
            $text = $errors->getText();

            if (!empty($text)) {
                throw new \RuntimeException(sprintf(
                    'The page "%s" contains following error messages: "%s"',
                    $this->getSession()->getCurrentUrl(),
                    $text
                ));
            }
        }

        /* @var \Behat\Mink\Element\NodeElement $form_element */
        foreach ($page->findAll('css', 'input, select, textarea') as $form_element) {
            if ($form_element->hasClass('error')) {
                throw new \Exception(sprintf('Element "#%s" has an error class.', $form_element->getAttribute('id')));
            }
        }
    }

    /**
     * Open the page with specified resolution.
     *
     * @param string $width_height
     *   String that satisfy the condition "<WIDTH>x<HEIGHT>".
     *
     * @example
     *   Given I should use the "1280x800" resolution
     *
     * @Given /^(?:|I should )use the "([^"]*)" screen resolution$/
     */
    public function useScreenResolution($width_height)
    {
        list($width, $height) = explode('x', $width_height);

        $this->getSession()->getDriver()->resizeWindow((int) $width, (int) $height);
    }

    /**
     * Press the element using CSS selector.
     *
     * @param string $selector
     *   CSS selector of element.
     *
     * @throws \WebDriver\Exception\NoSuchElement
     *   When element was not found.
     *
     * @When /^(?:|I )press "([^"]*)" element$/
     */
    public function pressElement($selector)
    {
        $page = $this->getWorkingElement();
        $element = $page->find('css', $selector);

        if (!$element) {
            $element = $page->findButton($selector);
        }

        $this->throwNoSuchElementException($selector, $element);
        $this->unsetWorkingElement();
        $element->press();
    }

    /**
     * @param string $text
     *   Text to search in region (block).
     * @param string $selector
     *   CSS selector or region name.
     *
     * @Given /^(?:|I )press on element with text "([^"]*)"(?:| in "([^"]*)"(?:| region))$/
     */
    public function pressElementByText($text, $selector = null)
    {
        $region = $selector ? $this->findElementBySelectors(['region'], $selector) : $this->getWorkingElement();
        $element = $region->find('xpath', "//*[text()[starts-with(., '$text')]]");
        $this->throwNoSuchElementException($text, $element);

        $element->press();
    }

    /**
     * @Given /^(?:|I )wait until AJAX is finished$/
     *
     * @javascript
     */
    public function waitUntilAjaxIsFinished()
    {
        $this->waitAjaxAndAnimations();
    }

    /**
     * @param string $selector
     *   CSS selector or region name.
     *
     * @Then /^(?:|I )work with elements in "([^"]*)"(?:| region)$/
     */
    public function workWithElementsInRegion($selector)
    {
        $region = $this->findElementBySelectors(['region'], $selector);

        $this->throwNoSuchElementException($selector, $region);
        $this->setWorkingElement($region);
    }

    /**
     * @Then /^I checkout to whole page$/
     */
    public function unsetWorkingElementScope()
    {
        $this->unsetWorkingElement();
    }

    /**
     * @param int $seconds
     *   Amount of seconds when nothing to happens.
     *
     * @Given /^(?:|I )wait (\d+) seconds$/
     */
    public function waitSeconds($seconds)
    {
        sleep($seconds);
    }

    /**
     * @BeforeSuite @api
     */
    public static function beforeSuite()
    {
        self::setDrupalVariables([
            // Set to "FALSE", because the administration menu will not be rendered.
            // https://www.drupal.org/node/2023625#comment-8607207
            'admin_menu_cache_client' => false,
        ]);
    }

    /**
     * @param BeforeScenarioScope $scope
     *
     * @BeforeScenario
     */
    public function beforeScenario(BeforeScenarioScope $scope)
    {
        $this->tags += array_merge($scope->getFeature()->getTags(), $scope->getScenario()->getTags());
        // Any page should be visited due to using jQuery and checking the cookies.
        $this->getSession()->visit($this->locatePath('/'));
    }

    /**
     * Set the jQuery handlers for "start" and "finish" events of AJAX queries.
     * In each method can be used the "waitAjaxAndAnimations" method for check
     * that AJAX was finished.
     *
     * @see waitAjaxAndAnimations()
     *
     * @BeforeScenario @javascript
     */
    public function beforeScenarioJS()
    {
        $javascript = '';

        foreach (['Start' => 'true', 'Complete' => 'false'] as $name => $state) {
            $javascript .= "jQuery(document).bind('ajax$name',function(){window.__behatAjax=$state});";
        }

        $this->getSession()->executeScript($javascript);
    }

    /**
     * @param AfterStepScope $step
     *
     * @AfterStep @javascript
     */
    public function afterIWaitUntilAjaxIsFinished(AfterStepScope $step)
    {
        if (self::isStepImpliesJsEvent($step)) {
            $this->waitAjaxAndAnimations();
        }
    }
}
