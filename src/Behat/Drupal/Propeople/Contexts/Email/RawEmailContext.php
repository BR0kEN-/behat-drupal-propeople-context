<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Contexts\Email;

// Contexts.
use Behat\Drupal\Propeople\Contexts\RawPropeopleContext;

class RawEmailContext extends RawPropeopleContext
{
    private $originalMailSystem = array('default-system' => 'DefaultMailSystem');
    private $messages = array();

    /**
     * @BeforeScenario @emails
     */
    public function initializeEmailTesting()
    {
        // Store the original system to restore after the scenario.
        $this->originalMailSystem = variable_get('mail_system', $this->originalMailSystem);
        $this->setDrupalVariables(array(
          // Set the mail system for testing. It will store an emails in
          // "drupal_test_email_collector" Drupal variable instead of sending.
          'mail_system' => array('default-system' => 'TestingMailSystem'),
          // Set to "FALSE", because the administration menu will not be rendered.
          // https://www.drupal.org/node/2023625#comment-8607207
          'admin_menu_cache_client' => false,
        ));
    }

    /**
     * @AfterScenario @emails
     */
    public function restoreEmailSubmissionSettings()
    {
        $this->setDrupalVariables(array(
          // Bring back the original mail system.
          'mail_system' => $this->originalMailSystem,
          // Flush the email buffer, allowing us to reuse this step
          // definition to clear existing mail.
          'drupal_test_email_collector' => array(),
        ));
    }

    /**
     * @throws \RangeException
     *   When no one message was sent.
     *
     * @return array
     *   An array of messages that was sent.
     */
    public function getEmailMessages()
    {
        // We can't use variable_get() because $conf is only
        // fetched once per scenario.
        if (!$this->messages) {
            $this->messages = db_select('variable', 'v')
              ->fields('v', array('value'))
              ->condition('name', 'drupal_test_email_collector', '=')
              ->execute()
              ->fetchField();

            $this->messages = unserialize($this->messages);
        }

        if (!$this->messages) {
            throw new \RangeException('No one message was not sent.');
        }

        return $this->messages;
    }
}
