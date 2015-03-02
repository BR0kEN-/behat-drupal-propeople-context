<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Wysiwyg;

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
        $this->wysiwyg = null;
    }

    /**
     * @param string $text
     * @param string $selector
     *
     * @throws \Exception
     *   When editor was not found.
     *
     * @javascript @wysiwyg
     * @Given /^(?:|I )fill "([^"]*)" in (?:|"([^"]*)" )WYSIWYG editor$/
     */
    public function setData($text, $selector = null)
    {
        $this->executeWysiwygMethod(__FUNCTION__, array($text), $selector);
    }

    /**
     * @param string $text
     * @param string $selector
     *
     * @throws \Exception
     *   When editor was not found.
     *
     * @javascript @wysiwyg
     * @When /^(?:|I )type "([^"]*)" in (?:|"([^"]*)" )WYSIWYG editor$/
     */
    public function insertText($text, $selector = null)
    {
        $this->executeWysiwygMethod(__FUNCTION__, array($text), $selector);
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
     * @javascript @wysiwyg
     * @Then /^(?:|I )should(| not) see "([^"]*)" in (?:|"([^"]*)" )WYSIWYG editor$/
     */
    public function getData($condition, $text, $selector = null)
    {
        $condition = (bool) $condition;

        if (strpos($this->executeWysiwygMethod(__FUNCTION__, null, $selector), $text) === $condition) {
            throw new \RuntimeException(
                sprintf('The text "%s" was %s found in the "%s" WYSIWYG editor.',
                $text,
                $condition ? '' : 'not',
                $this->getEditorSelector()
            ));
        }
    }
}
