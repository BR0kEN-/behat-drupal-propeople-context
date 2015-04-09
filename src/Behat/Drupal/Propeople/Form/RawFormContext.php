<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Form;

// Helpers.
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\DocumentElement;

// Contexts.
use Behat\Drupal\Propeople\RawPropeopleContext;

class RawFormContext extends RawPropeopleContext
{
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
        $labels = $fields = [];

        /* @var NodeElement $label */
        foreach ($page->findAll('xpath', "//label[starts-with(text(), '$text')]") as $label) {
            $element_id = $label->getAttribute('for');

            if ($element_id) {
                $labels[] = $label;
            }

            // We trying to find an ID with "-upload" suffix, because some
            // image inputs in Drupal are suffixed by it.
            foreach ([$element_id, "$element_id-upload"] as $element_id) {
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
}
