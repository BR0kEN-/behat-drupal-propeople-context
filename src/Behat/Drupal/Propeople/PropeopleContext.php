<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople;

// Contexts.
use Behat\Behat\Context\SnippetAcceptingContext;

// Exceptions.
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

// Helpers.
use WebDriver\Service\CurlService;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Snippet\Snippet;

class PropeopleContext extends RawPropeopleContext implements SnippetAcceptingContext
{
    const PARSE_STRING = '(.+?)';
    private $mailAccountStrings = null;

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
     * @param string $name
     *   An iframe name (null for switching back).
     *
     * @Given /^(?:|I )switch to an iframe "([^"]*)"$/
     */
    public function iSwitchToAnIframe($name = null)
    {
        $this->getSession()->switchToIFrame($name);
    }

    /**
     * @Given /^(?:|I )switch back from an iframe$/
     */
    public function iSwitchBackFromAnIframe()
    {
        $this->iSwitchToAnIframe();
    }

    /**
     * Check that an image was uploaded and can be viewed on the page.
     *
     * @Then /^(?:|I )should see the thumbnail$/
     */
    public function iShouldSeeTheThumbnail()
    {
        $page = $this->getSession()->getPage();
        $thumb = false;

        foreach (array('media-thumbnail', 'image-preview') as $classname) {
            if (!$thumb) {
                $thumb = $page->find('css', ".$classname img");
            }
        }

        if (!$thumb) {
            throw new \Exception('An expected image tag was not found.');
        }

        $file = explode('?', $thumb->getAttribute('src'));
        $file = $this->getFilesUrl() . '/' . reset($file);

        $curl = new CurlService();
        list(, $info) = $curl->execute('GET', $file);

        if (empty($info) || strpos($info['content_type'], 'image/') === false) {
            throw new FileNotFoundException(sprintf('%s did not return an image', $file));
        }
    }

    /**
     * Check that the page have no error messages and fields - error classes.
     *
     * @Then /^I should see no errors$/
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
    public function useTheScreenResolution($width_height)
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
        $element = $this->getSession()->getPage()->find('css', $selector);

        if (!$element) {
            $this->throwNoSuchElementException($selector);
        }

        $element->press();
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

        $this->getSession()->getPage()->fillField($field, $value);
    }

    /**
     * Type something to field with autocomplete, wait the result and choose one.
     *
     * @param string $value
     *   Typed text.
     * @param string $field
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
     *
     * @javascript
     * @Then /^(?:|I )typed "([^"]*)" in the "([^"]*)" field and chose (\d+) option from autocomplete variants$/
     */
    public function choseOptionFromAutocompleteVariants($value, $field, $option)
    {
        if (!$option) {
            throw new \InvalidArgumentException(sprintf(
                'An option that will be chosen expected as positive number, but was got the: %s',
                $option
            ));
        }

        $this->getSession()->getPage()->fillField($field, $value);
        $this->waitUntilAjaxIsFinished();

        $autocomplete = $this->getSession()->getPage()->findById('autocomplete');

        if (!$autocomplete) {
            $this->throwNoSuchElementException('#autocomplete');
        }

        $options = $autocomplete->findAll('css', 'li');

        if (!$options) {
            throw new \RuntimeException('Neither option was not loaded.');
        }

        $options_number = count($options);

        if ($option > $options_number) {
            throw new \OverflowException(sprintf(
                'You can not select option %s, as there are only %d.',
                $option,
                $options_number
            ));
        }

        $mink_context = $this->getMinkContext();

        for ($i = 0; $i < $option; $i++) {
            $mink_context->pressKey('down', $field);
        }

        $mink_context->pressKey('enter', $field);
    }

    /**
     * Check JS events in step definition.
     *
     * @param Snippet $event
     *
     * @return int
     */
    public static function isStepImpliesJsEvent(Snippet $event)
    {
        return preg_match('/(follow|press|click|submit)/i', $event->getStep()->getText());
    }

    /**
     * Set the global handlers for "ajaxStart" and "ajaxComplete" events.
     *
     * @param Snippet $event
     *
     * @BeforeStep @javascript
     */
    public function beforeIWaitUntilAjaxIsFinished(Snippet $event)
    {
        if (self::isStepImpliesJsEvent($event)) {
            $javascript = '';

            foreach (array('Start' => 'false', 'Complete' => 'true') as $name => $state) {
                $javascript .= "jQuery(document).one('ajax$name',function(){window.__behatAjax=$state});";
            }

            $this->getSession()->executeScript($javascript);
        }
    }

    /**
     * @param Snippet $event
     *
     * @AfterStep @javascript
     */
    public function afterIWaitUntilAjaxIsFinished(Snippet $event)
    {
        if (self::isStepImpliesJsEvent($event)) {
            $this->waitUntilAjaxIsFinished();
        }
    }

    /**
     * @javascript
     * @Given /^I wait until AJAX is finished$/
     */
    public function waitUntilAjaxIsFinished()
    {
        $this->getSession()->wait(3000, 'window.__behatAjax === true');
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
     * @param string $selector
     *   Field selector.
     *
     * @throws \WebDriver\Exception\NoSuchElement
     *   When radio button was not found.
     *
     * @Given /^(?:|I )check the "([^"]*)" radio button$/
     */
    public function radioAction($selector)
    {
        $field = $this->getSession()->getPage()->findField($selector);

        if (!$field) {
            $this->throwNoSuchElementException($selector);
        }

        $field->selectOption($field->getAttribute('value'));
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
     * @param string $to
     *   Email address on that should have been sent a letter.
     *
     * @emails
     * @Given /^(?:|I )check that email to "([^"]*)" was sent$/
     */
    public function checkThatEmailToWasSent($to)
    {
        foreach ($this->getEmailMessages() as $message) {
            if ($message['to'] != $to) {
                throw new \RuntimeException(sprintf('The message was not sent to "%s".', $to));
            }
        }
    }

    /**
     * @param string $text
     *   Text that need to be found in letter.
     *
     * @emails
     * @Given /^(?:|I )check that email body contains the "([^"]*)" text$/
     */
    public function checkThatEmailBodyContainsTheText($text)
    {
        foreach ($this->getEmailMessages() as $message) {
            if (strpos($message['body'], $text) === false) {
                throw new \RuntimeException('Did not find expected content in message body.');
            }
        }
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
     * @emails
     * @Given /^(?:|I )login with user credentials that was sent via email$/
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
                $matches = array();

                foreach (explode(PHP_EOL, $message['body']) as $string) {
                    foreach ($regexps as $name => $regexp) {
                        if ($regexp && preg_match("/^$regexp$/i", $string, $match)) {
                            $matches[$name] = $match[1];
                        }
                    }
                }

                if (!empty($matches['username']) && !empty($matches['password'])) {
                    $session = $this->getSession();
                    $session->visit($this->locatePath('/user/login'));
                    $page = $session->getPage();

                    foreach ($matches as $name => $credential) {
                        $page->fillField($this->getDrupalText($name . '_field'), $credential);
                    }

                    $button_text = $this->getDrupalText('log_in');
                    $submit = $page->findButton($button_text);

                    if (!$submit) {
                        $this->throwNoSuchElementException(sprintf('%s text', $button_text));
                    }

                    // Log in.
                    $submit->click();

                    if (!$this->loggedIn()) {
                        throw new \Exception(sprintf(
                            'Failed to login as user "%s" with password "%s"',
                            $matches['username'],
                            $matches['password']
                        ));
                    }

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
