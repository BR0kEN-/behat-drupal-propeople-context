<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Email;

// Contexts.
use Behat\Drupal\Propeople\RawPropeopleContext;

class RawEmailContext extends RawPropeopleContext
{
    private $originalMailSystem = array('default-system' => 'DefaultMailSystem');
    private $messages = array();

    /**
     * @BeforeScenario @email
     */
    public function initializeEmailTesting()
    {
        // Store the original system to restore after the scenario.
        $this->originalMailSystem = variable_get('mail_system', $this->originalMailSystem);
        $this->setDrupalVariables(array(
          // Set the mail system for testing. It will store an emails in
          // "drupal_test_email_collector" Drupal variable instead of sending.
          'mail_system' => array('default-system' => 'TestingMailSystem'),
        ));
    }

    /**
     * @AfterScenario @email
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
        // We can't use variable_get() because Behat has another bootstrapped
        // variable $conf that is not updated from curl bootstrapped
        // Drupal instance.
        if (empty($this->messages)) {
            $this->messages = db_select('variable', 'v')
              ->fields('v', array('value'))
              ->condition('name', 'drupal_test_email_collector', '=')
              ->execute()
              ->fetchField();

            $this->messages = unserialize($this->messages);
        }

        if (empty($this->messages)) {
            throw new \RangeException('No one message was sent.');
        }

        return $this->messages;
    }
}
