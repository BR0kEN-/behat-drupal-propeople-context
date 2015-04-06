<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople;

// Exceptions.
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

// Helpers.
use Behat\Behat\Hook\Scope\AfterStepScope;
use WebDriver\Service\CurlService;
use Behat\Gherkin\Node\TableNode;

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
     * @Then /^(?:|I )should see no errors$/
     */
    public function iShouldSeeNoErrors()
    {
        $page = $this->getSession()->getPage();
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
     * @Given /^(?:|I )press on element with text "([^"]*)"(?:| in "([^"]*)"(?:| region))$/
     */
    public function pressElementByText($text, $selector = null)
    {
        $region = $selector ? $this->findElementBySelectors(array('region'), $selector) : $this->getWorkingElement();
        $element = $region->find('xpath', "//*[text()[starts-with(., '$text')]]");
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
     * @param string $selector
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
     * @throws \Exception
     *   When value was not changed.
     *
     * @javascript
     * @Then /^(?:|I )typed "([^"]*)" in the "([^"]*)" field and chose (\d+) option from autocomplete variants$/
     */
    public function choseOptionFromAutocompleteVariants($value, $selector, $option)
    {
        if (!$option) {
            throw new \InvalidArgumentException(sprintf(
                'An option that will be chosen expected as positive number, but was got the: %s',
                $option
            ));
        }

        $field = $this->findField($selector, $this->getWorkingElement());
        $this->throwNoSuchElementException($selector, $field);

        $this->getSession()->executeScript(sprintf(
            "jQuery('#%s').val('%s').keyup();",
            $field->getAttribute('id'),
            $value
        ));
        $this->waitAjaxAndAnimations();

        $autocomplete = $field->getParent()->findById('autocomplete');
        $this->throwNoSuchElementException('#autocomplete', $autocomplete);

        $options = count($autocomplete->findAll('css', 'li'));

        if (!$options) {
            throw new \RuntimeException('Neither option was not loaded.');
        }

        if ($option > $options) {
            throw new \OverflowException(sprintf(
                'You can not select an option %s, as there are only %d.',
                $option,
                $options
            ));
        }

        for ($i = 0; $i < $option; $i++) {
            // 40 - down
            $field->keyDown(40);
            $field->keyUp(40);
        }

        // 13 - enter
        $field->keyDown(13);
        $field->keyUp(13);

        if ($field->getValue() == $value) {
            throw new \Exception(sprintf('The value of "%s" field was not changed.', $selector));
        }
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
    public function beforeScenario()
    {
        $session = $this->getSession();
        // Any page should be visited due to using jQuery.
        $session->visit($this->locatePath('/'));

        $javascript = '';

        foreach (array('Start' => 'true', 'Complete' => 'false') as $name => $state) {
            $javascript .= "jQuery(document).bind('ajax$name',function(){window.__behatAjax=$state});";
        }

        $session->executeScript($javascript);
    }

    /**
     * @param AfterStepScope $step
     *
     * @javascript
     * @AfterStep
     */
    public function afterIWaitUntilAjaxIsFinished(AfterStepScope $step)
    {
        if (self::isStepImpliesJsEvent($step)) {
            $this->waitAjaxAndAnimations();
        }
    }

    /**
     * @javascript
     * @Given /^(?:|I )wait until AJAX is finished$/
     */
    public function waitUntilAjaxIsFinished()
    {
        $this->waitAjaxAndAnimations();
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
     * @param string $customized
     *   Can be an empty string or " customized".
     * @param string $selector
     *   Field selector.
     *
     * @throws \WebDriver\Exception\NoSuchElement
     *   When radio button was not found.
     * @throws \Exception
     *
     * @Given /^(?:|I )check the(| customized) "([^"]*)" radio button$/
     */
    public function radioAction($customized, $selector)
    {
        $page = $this->getWorkingElement();
        $field = $page->findField($selector);
        $customized = (bool) $customized;

        // Try to find a label of radio button if it custom and hidden or if field was not found.
        if (($field && $customized && !$field->isVisible()) || !$field) {
            // Find all labels of a radio button or only first, if it is not custom.
            foreach ($this->findFieldLabels($selector, !$customized) as $label) {
                // Check a custom label for visibility.
                if ($customized && !$label->isVisible()) {
                    continue;
                }

                $label->click();
                return;
            }
        } elseif ($field) {
            $field->click();
            return;
        }

        $this->throwNoSuchElementException($selector, $field);
    }

    /**
     * @param string $selector
     * @param string $value
     *
     * @When /^(?:|I )fill "([^"]*)" with "([^"]*)"$/
     */
    public function fillField($selector, $value)
    {
        $field = $this->findField($selector);
        $this->throwNoSuchElementException($selector, $field);
        $field->setValue($value);
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
     * @Given /^(?:|I )attach file "([^"]*)" to "([^"]*)"$/
     */
    public function attachFile($file, $selector)
    {
        $files_path = $this->getMinkParameter('files_path');

        if (!$files_path) {
            throw new \Exception('The "files_path" Mink parameter was not configured.');
        }

        $file = rtrim(realpath($files_path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;

        if (!is_file($file)) {
            throw new \InvalidArgumentException(sprintf('The "%s" file does not exist.', $file));
        }

        $field = $this->findField($selector);
        $this->throwNoSuchElementException($selector, $field);
        $field->attachFile($file);
    }

    /**
     * @param string $selector
     * @param TableNode $values
     *
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     * @throws \Exception
     * @throws \WebDriver\Exception\NoSuchElement
     *
     * @javascript
     * @Given /^(?:|I )select the following in "([^"]*)" hierarchical select:$/
     */
    public function setValueForHierarchicalSelect($selector, TableNode $values)
    {
        $element = $this->getWorkingElement();
        $wrapper = $element->findById($selector);

        if ($wrapper) {
            $labels = $wrapper->findAll('xpath', '//label[@for]');
        } else {
            $labels = $this->findFieldLabels($selector, true);
        }

        if (!$labels) {
            throw new \Exception('No one hierarchical select was found.');
        }

        /* @var \Behat\Mink\Element\NodeElement $label */
        $label = reset($labels);
        $parent = $label->getParent();

        foreach (array_keys($values->getRowsHash()) as $i => $value) {
            $selects = array();
            $select = null;

            foreach ($parent->findAll('css', 'select') as $select) {
                if ($select->isVisible()) {
                    $selects[] = $select;
                }
            }

            if (!isset($selects[$i])) {
                throw new \InvalidArgumentException(sprintf(
                    'The value "%s" was specified for select #%s but it does not exist.',
                    $value,
                    $i
                ));
            }

            $selects[$i]->selectOption($value);
            $this->waitAjaxAndAnimations();
        }
    }
}
