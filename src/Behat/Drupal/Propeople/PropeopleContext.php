<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople;

// Contexts.
use Behat\Behat\Context\SnippetAcceptingContext;

// Exceptions.
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

// Helpers.
use WebDriver\Service\CurlService;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Snippet\Snippet;

class PropeopleContext extends RawPropeopleContext implements SnippetAcceptingContext
{
    /**
     * @param string $name
     *   An iframe name (null for switching back).
     *
     * @Given /^(?:|I )switch to an iframe "([^"]*)"$/
     */
    public function iSwitchToAnIframe($name = null)
    {
        $this->getSession()->switchToIFrame($name);
    }

    /**
     * @Given /^(?:|I )switch back from an iframe$/
     */
    public function iSwitchBackFromAnIframe()
    {
        $this->iSwitchToAnIframe();
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
            if (!$thumb) {
                $thumb = $page->find('css', ".$classname img");
            }
        }

        if (!$thumb) {
            throw new \Exception('An expected image tag was not found.');
        }

        $file = explode('?', $thumb->getAttribute('src'));
        $file = $this->getFilesUrl() . '/' . reset($file);

        $curl = new CurlService();
        list(, $info) = $curl->execute('GET', $file);

        if (empty($info) || strpos($info['content_type'], 'image/') === false) {
            throw new FileNotFoundException(sprintf('%s did not return an image', $file));
        }
    }

    /**
     * Check that the page have no error messages and fields - error classes.
     *
     * @Then /^(?:|I )should see no errors$/
     */
    public function iShouldSeeNoErrors()
    {
        $page = $this->getWorkingElement();
        $errors = $page->find('css', $this->getDrupalSelector('error_message_selector'));

        // Some modules are inserted an empty container for errors before
        // they are arise. The "Clientside Validation" - one of them.
        if ($errors) {
            $text = $errors->getText();

            if (!empty($text)) {
                throw new \Exception(sprintf(
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
     * @Given /^(?:|I )press on element with text "([^"]*)" in "([^"]*)"(?:| region)$/
     */
    public function pressElementByText($text, $selector)
    {
        $region = $this->findElementBySelectors(array('region'), $selector);
        $this->throwNoSuchElementException($selector, $region);

        $element = $region->find('xpath', "//*[text()[contains(., '$text')]]");
        $this->throwNoSuchElementException($text, $element);

        $element->press();
    }

    /**
     * Use the current user data for filling fields.
     *
     * @todo Improve logic of the method.
     *
     * @param string $field
     *   The name of field to fill in.
     * @param string $user_field
     *   The name of field of user entity.
     *
     * @throws \InvalidArgumentException
     *   When $filed does not exist in user entity.
     * @throws \UnexpectedValueException
     *   When value of field of user entity is empty.
     * @throws \Exception
     *   When user is anonymous.
     *
     * @Then /^(?:I )fill in "([^"]*)" with value of field "([^"]*)" of current user$/
     */
    public function fillInWithValueOfFieldOfCurrentUser($field, $user_field)
    {
        if ($this->user && !$this->user->uid) {
            throw new \Exception('Anonymous user have no fields');
        }

        $drupal_user = user_load($this->user->uid);

        if (empty($drupal_user->$user_field)) {
            throw new \InvalidArgumentException(sprintf('User entity has no "%s" field.', $user_field));
        }

        $value = $drupal_user->$user_field;

        if (is_array($value)) {
            $value = field_view_field('user', $drupal_user, $user_field);
            $value = reset($value[0]);
        }

        if (empty($value)) {
            throw new \UnexpectedValueException('The value of "%s" field is empty.', $user_field);
        }

        $this->getWorkingElement()->fillField($field, $value);
    }

    /**
     * Type something to field with autocomplete, wait the result and choose one.
     *
     * @param string $value
     *   Typed text.
     * @param string $field
     *   Selector of the field.
     * @param int $option
     *   An option number. Will be selected from loaded variants.
     *
     * @example
     *   Then I typed "a" in the "field_related[und][0][nid]" field and chose 3 option from autocomplete variants
     * The same, without this method:
     *   Then I fill in "field_related[und][0][nid]" with "a"
     *   And I wait for AJAX to finish
     *   And I press the "down" key in the "field_related[und][0][nid]" field
     *   And I press the "down" key in the "field_related[und][0][nid]" field
     *   And I press the "down" key in the "field_related[und][0][nid]" field
     *   And I press the "enter" key in the "field_related[und][0][nid]" field
     *
     * @throws \InvalidArgumentException
     *   When $option is less than zero.
     * @throws \WebDriver\Exception\NoSuchElement
     *   When autocomplete list was not loaded.
     * @throws \RuntimeException
     *   When neither option was not loaded.
     * @throws \OverflowException
     *   When $option is more than variants are available.
     *
     * @javascript
     * @Then /^(?:|I )typed "([^"]*)" in the "([^"]*)" field and chose (\d+) option from autocomplete variants$/
     */
    public function choseOptionFromAutocompleteVariants($value, $field, $option)
    {
        if (!$option) {
            throw new \InvalidArgumentException(sprintf(
                'An option that will be chosen expected as positive number, but was got the: %s',
                $option
            ));
        }

        $page = $this->getWorkingElement();

        $page->fillField($field, $value);
        $this->waitUntilAjaxIsFinished();

        $autocomplete = $page->findById('autocomplete');
        $this->throwNoSuchElementException('#autocomplete', $autocomplete);

        $options = $autocomplete->findAll('css', 'li');

        if (!$options) {
            throw new \RuntimeException('Neither option was not loaded.');
        }

        $options_number = count($options);

        if ($option > $options_number) {
            throw new \OverflowException(sprintf(
                'You can not select option %s, as there are only %d.',
                $option,
                $options_number
            ));
        }

        $mink_context = $this->getMinkContext();

        for ($i = 0; $i < $option; $i++) {
            $mink_context->pressKey('down', $field);
        }

        $mink_context->pressKey('enter', $field);
    }

    /**
     * Set the global handlers for "ajaxStart" and "ajaxComplete" events.
     *
     * @param Snippet $event
     *
     * @BeforeStep @javascript
     */
    public function beforeIWaitUntilAjaxIsFinished(Snippet $event)
    {
        if (self::isStepImpliesJsEvent($event)) {
            $javascript = '';

            foreach (array('Start' => 'false', 'Complete' => 'true') as $name => $state) {
                $javascript .= "jQuery(document).one('ajax$name',function(){window.__behatAjax=$state});";
            }

            $this->getSession()->executeScript($javascript);
        }
    }

    /**
     * @param Snippet $event
     *
     * @AfterStep @javascript
     */
    public function afterIWaitUntilAjaxIsFinished(Snippet $event)
    {
        if (self::isStepImpliesJsEvent($event)) {
            $this->waitUntilAjaxIsFinished();
        }
    }

    /**
     * @javascript
     * @Given /^I wait until AJAX is finished$/
     */
    public function waitUntilAjaxIsFinished()
    {
        $this->getSession()->wait(3000, 'window.__behatAjax === true');
    }

    /**
     * @param string $action
     *   Can be "check" or "uncheck".
     * @param TableNode $checkboxes
     *   Table with one row of checkboxes selectors.
     *
     * @Given /^(?:|I )(?:|un)check the boxes:/
     */
    public function checkboxAction($action, TableNode $checkboxes)
    {
        $mink_context = $this->getMinkContext();

        foreach ($checkboxes->getRows() as $checkbox) {
            $mink_context->{trim($action) . 'Option'}(reset($checkbox));
        }
    }

    /**
     * This method was defined and used instead of "assertSelectRadioById",
     * because the field label can contain too long value and better to use
     * another selector instead of label.
     *
     * @see assertSelectRadioById()
     *
     * @param string $selector
     *   Field selector.
     *
     * @throws \WebDriver\Exception\NoSuchElement
     *   When radio button was not found.
     * @throws \Exception
     *
     * @javascript
     * @Given /^(?:|I )check the "([^"]*)" radio button$/
     */
    public function radioAction($selector)
    {
        $page = $this->getWorkingElement();
        $field = $page->findField($selector);

        if (!$field || !$field->isVisible()) {
            /* @var \Behat\Mink\Element\NodeElement $field */
            foreach ($page->findAll('xpath', "//label[contains(text(), '$selector')]") as $field) {
                if ($field->isVisible()) {
                    $element_id = $field->getAttribute('for');

                    if (!$element_id || !$page->findById($element_id)) {
                        throw new \Exception(
                            'The label of a field has no "for" attribute or an
                            element cannot be found by value from that attribute.'
                        );
                    } else {
                        $field->click();
                    }

                    return;
                }
            }
        }

        $this->throwNoSuchElementException($selector, $field);
        $field->selectOption($field->getAttribute('value'));
    }

    /**
     * @param string $field
     * @param string $value
     *
     * @When /^(?:|I )fill "([^"]*)" with "([^"]*)"$/
     */
    public function fillField($field, $value)
    {
        $this->getWorkingElement()->fillField($field, $value);
    }

    /**
     * @param TableNode $fields
     *   | Field locator | Value |
     *
     * @When /^(?:|I )fill the following:$/
     */
    public function fillFields(TableNode $fields)
    {
        foreach ($fields->getRowsHash() as $field => $value) {
            $this->fillField($field, $value);
        }
    }

    /**
     * @param string $selector
     *   CSS selector or region name.
     *
     * @Then /^(?:|I )work with elements in "([^"]*)"(?:| region)$/
     */
    public function workWithElementsInRegion($selector)
    {
        $region = $this->findElementBySelectors(array('region'), $selector);

        $this->throwNoSuchElementException($selector, $region);
        $this->setWorkingElement($region);
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
}
