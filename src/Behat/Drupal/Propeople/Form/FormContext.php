<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Form;

// Helpers.
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Drupal\Propeople\User\UserEntity;

class FormContext extends RawFormContext
{
    use UserEntity;

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
     * Use the current user data for filling fields.
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
     *
     * @api
     */
    public function fillInWithValueOfFieldOfCurrentUser($field, $user_field)
    {
        if ($this->user && !$this->user->uid) {
            throw new \Exception('Anonymous user have no fields');
        }

        $entity = self::entityWrapper($this->user->uid);

        if (empty($entity->$user_field)) {
            throw new \InvalidArgumentException(sprintf('User entity has no "%s" field.', $user_field));
        }

        $value = $entity->{$user_field}->value();

        if (empty($value)) {
            throw new \UnexpectedValueException('The value of "%s" field is empty.', $user_field);
        }

        $this->getWorkingElement()->fillField($field, $value);
    }

    /**
     * @todo needs example.
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

        /* @var NodeElement $label */
        $label = reset($labels);
        $parent = $label->getParent();

        foreach (array_keys($values->getRowsHash()) as $i => $value) {
            /* @var NodeElement[] $selects */
            $selects = [];
            /* @var NodeElement $select */
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
