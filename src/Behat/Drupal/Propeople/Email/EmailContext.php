<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Email;

class EmailContext extends RawEmailContext
{
    const PARSE_STRING = '(.+?)';
    private $mailAccountStrings;

    /**
     * @param callable $mail_account_strings
     *   This function must return an associative array with two keys: "username" and "password".
     *   The value of each key should be a string with placeholder that will replaced with user
     *   login and password from an account. In testing, placeholders will be replaced by regular
     *   expressions for parse the message that was sent.
     *
     * @see loginWithUserCredentialsThatWasSentViaEmail()
     *
     * @code
     * function mail_account_strings($name, $pass) {
     *     return array(
     *       'username' => t('Username: !mail', array('!mail' => $name)),
     *       'password' => t('Password: !pass', array('!pass' => $pass)),
     *     );
     * }
     *
     * function hook_mail($key, &$message, $params) {
     *     switch ($key) {
     *         case 'account':
     *             $message['subject'] = t('Website Account');
     *             $message['body'][] = t('You can login on the site using next credentials:');
     *             $message['body'] += mail_account_strings($params['mail'], $params['pass']);
     *         break;
     *     }
     * }
     * @code
     */
    public function __construct($mail_account_strings = null)
    {
        $this->mailAccountStrings = $mail_account_strings;
    }

    /**
     * @param string $to
     *   Email address on that should have been sent a letter.
     *
     * @Given /^(?:|I )check that email to "([^"]*)" was sent$/
     *
     * @return boolean
     *   True if email message for given email address was found.
     *
     * @throws \RuntimeException
     *
     * @email @api
     */
    public function checkThatEmailToWasSent($to)
    {
        foreach ($this->getEmailMessages() as $message) {
            if ($message['to'] === $to) {
                return true;
            }
        }

        throw new \RuntimeException(sprintf('The message was not sent to "%s" email.', $to));
    }

    /**
     * @param string $text
     *   Text that need to be found in letter.
     *
     * @Given /^(?:|I )check that email body contains the "([^"]*)" text$/
     *
     * @return boolean
     *   True if email with given text was found.
     *
     * @throws \RuntimeException
     *
     * @email @api
     */
    public function checkThatEmailBodyContainsTheText($text)
    {
        foreach ($this->getEmailMessages() as $message) {
            if (strpos($message['body'], $text) !== false) {
                return false;
            }
        }

        throw new \RuntimeException('Did not find expected content in any messages body.');
    }

    /**
     *
     * @throws \Exception
     *   When parameter "parse_mail_callback" was not specified.
     * @throws \InvalidArgumentException
     *   When parameter "parse_mail_callback" is not callable.
     * @throws \WebDriver\Exception\NoSuchElement
     *   When "Log in" button cannot be found on the page.
     * @throws \RuntimeException
     *   When credentials cannot be parsed or does not exist.
     *
     * @Given /^(?:|I )login with user credentials that was sent via email$/
     *
     * @email @api
     */
    public function loginWithUserCredentialsThatWasSentViaEmail()
    {
        if (!$this->mailAccountStrings) {
            throw new \Exception(sprintf(
                'The parameter "parse_mail_callback" does not specified in "config.yml" for "%s" context.',
                __CLASS__
            ));
        }

        if (!is_callable($this->mailAccountStrings)) {
            throw new \InvalidArgumentException('The value of "parse_mail_callback" parameter is not callable.');
        }

        $success = false;

        foreach ($this->getEmailMessages() as $message) {
            if (!empty($message['body'])) {
                $regexps = call_user_func($this->mailAccountStrings, self::PARSE_STRING, self::PARSE_STRING);
                $matches = [];

                foreach (explode(PHP_EOL, $message['body']) as $string) {
                    foreach ($regexps as $name => $regexp) {
                        if ($regexp && preg_match("/^$regexp$/i", $string, $match)) {
                            $matches[$name] = $match[1];
                        }
                    }
                }

                if (!empty($matches['username']) && !empty($matches['password'])) {
                    $this->getUserContext()->fillLoginForm($matches);
                    $success = true;
                    break;
                }
            }
        }

        if (!$success) {
            throw new \RuntimeException(
                'Failed to login because email does not contain user credentials or they are was not parsed correctly.'
            );
        }
    }
}
