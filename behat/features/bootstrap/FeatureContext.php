<?php
/**
 * @file
 * Feature context.
 */

// Contexts.
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Drupal\Propeople\RawPropeopleContext;

// Helpers.
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

/**
 * Class FeatureContext.
 */
class FeatureContext extends RawPropeopleContext implements SnippetAcceptingContext {
  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {

  }
}
