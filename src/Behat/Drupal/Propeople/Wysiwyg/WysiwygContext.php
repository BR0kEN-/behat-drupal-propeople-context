<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Wysiwyg;

// Helpers.
use Behat\Gherkin\Node\TableNode;

/**
 * @todo Add TinyMCE support.
 */
class WysiwygContext extends RawWysiwygContext
{
    /**
     * @param string $selector
     *
     * @javascript @wysiwyg
     * @Given /^(?:|I )work with "([^"]*)" WYSIWYG editor$/
     */
    public function workWithEditor($selector)
    {
        $this->wysiwyg = $selector;
    }

    /**
     * @AfterScenario @wysiwyg
     */
    public function unsetWysiwyg()
    {
        $this->wysiwyg = '';
    }

    /**
     * @param string $text
     * @param string $selector
     *
     * @throws \Exception
     *   When editor was not found.
     *
     * @Given /^(?:|I )fill "([^"]*)" in (?:|"([^"]*)" )WYSIWYG editor$/
     *
     * @javascript @wysiwyg
     */
    public function setData($text, $selector = '')
    {
        $this->executeWysiwygMethod(__FUNCTION__, [$text], $selector);
    }

    /**
     * @param string $text
     * @param string $selector
     *
     * @throws \Exception
     *   When editor was not found.
     *
     * @When /^(?:|I )type "([^"]*)" in (?:|"([^"]*)" )WYSIWYG editor$/
     *
     * @javascript @wysiwyg
     */
    public function insertText($text, $selector = '')
    {
        $this->executeWysiwygMethod(__FUNCTION__, [$text], $selector);
    }

    /**
     * @param string $condition
     * @param string $text
     * @param string $selector
     *
     * @throws \Exception
     *   When editor was not found.
     * @throws \RuntimeException
     *   When text was[not] found.
     *
     * @Then /^(?:|I )should(| not) see "([^"]*)" in (?:|"([^"]*)" )WYSIWYG editor$/
     *
     * @javascript @wysiwyg
     */
    public function getData($condition, $text, $selector = '')
    {
        $condition = (bool) $condition;

        if (strpos($this->executeWysiwygMethod(__FUNCTION__, '', $selector), $text) === $condition) {
            throw new \RuntimeException(sprintf(
                'The text "%s" was %s found in the "%s" WYSIWYG editor.',
                $text,
                $condition ? '' : 'not',
                $this->getEditorSelector()
            ));
        }
    }

    /**
     * @param TableNode $fields
     *   | Editor locator | Value |
     *
     * @Then /^(?:|I )fill in following WYSIWYG editors:$/
     *
     * @javascript @wysiwyg
     */
    public function fillInMultipleEditors(TableNode $fields)
    {
        foreach ($fields->getRowsHash() as $editor => $value) {
            $this->setData($value, $editor);
        }
    }
}
