<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Wysiwyg;

// Contexts.
use Behat\Drupal\Propeople\RawPropeopleContext;

// Exceptions.
use WebDriver\Exception\NoSuchElement;

class RawWysiwygContext extends RawPropeopleContext
{
    protected $wysiwyg;
    private $selector;

    /**
     * Get the editor instance for use in Javascript.
     *
     * @param string $selector
     *   Any selector of a form field.
     *
     * @throws \RuntimeException
     * @throws \Exception
     * @throws NoSuchElement
     *
     * @javascript
     *
     * @return string
     *   A Javascript expression representing the WYSIWYG instance.
     */
    public function getWysiwygInstance($selector = null)
    {
        if (!$selector && !$this->wysiwyg) {
            throw new \RuntimeException('No such editor was not selected.');
        }

        $this->selector = $selector ?: $this->wysiwyg;
        $field = $this->getWorkingElement()->findField($this->selector);

        $this->throwNoSuchElementException($this->selector, $field);
        $id = $field->getAttribute('id');

        $instance = "CKEDITOR.instances['$id']";

        if (!$this->getSession()->evaluateScript("return !!$instance")) {
          throw new \Exception(sprintf('The editor "%s" was not found on the page %s', $id, $this->getSession()->getCurrentUrl()));
        }

        return $instance;
    }

    /**
     * @param string $method
     *   WYSIWYG editor method.
     * @param string|array $arguments
     *   Arguments for method of WYSIWYG editor.
     * @param string $selector
     *   Editor selector.
     *
     * @throws \Exception
     *   Throws an exception if the editor does not exist.
     *
     * @javascript
     *
     * @return string
     *   Result of JS evaluation.
     */
    public function executeWysiwygMethod($method, $arguments = '', $selector = null)
    {
        if ($arguments && is_array($arguments)) {
            $arguments = "'" . implode("','", $arguments) . "'";
        }

        $editor = $this->getWysiwygInstance($selector);
        return $this->getSession()->evaluateScript("$editor.$method($arguments);");
    }

    /**
     * @return string
     */
    protected function getEditorSelector()
    {
        return $this->selector;
    }
}
