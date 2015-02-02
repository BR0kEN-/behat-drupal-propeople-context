### Testing sending letters

Scenarios, which will use steps for testing emails, should be tagged with `@emails` tag.

```gherkin
Feature: Testing emails
  @emails
  Scenario: Create an account
    Given I am logged in as a user with the "administrator" role
    Then I am at "user/create"
    And I press the "Create" button
    Then I should see no errors
    And check that email to "test@propeople.com.ua" was sent
    And login with user credentials that was sent via email
```

#### Examples

Checking that email was sent on correct address.

```gherkin
Then I check that email to "test@propeople.com.ua" was sent
```

Checking that email body contains the text.

```gherkin
Then I check that email body contains the "Congratulations" text
```

If user credentials sent via email, then you can get their and try to login.

```gherkin
Then I login with user credentials that was sent via email
```

**IMPORTANT**: This method will works only when the context configured correctly.

Example of `config.yml`:
```yml
default:
  suites:
    default:
      contexts:
        - FeatureContext: ~
        - Behat\Drupal\Propeople\DrupalContext:
            mail_account_strings: mail_account_strings
        - Drupal\DrupalExtension\Context\MinkContext: ~
        - Drupal\DrupalExtension\Context\DrupalContext: ~
  extensions:
```

Example of `mail_account_strings()`:
```php
/**
 * The part of message with credentials that will be sent after registration.
 *
 * WARNING! This function is needed for correct translate of this part and
 * for usage in Behat testing. In "hook_mail()" this function should be
 * called with username and password as parameters and in testing - with
 * regexp for parse credentials.
 *
 * @param string $name
 *   User login or regexp to parse it.
 * @param string $pass
 *   User password or regexp to parse it.
 *
 * @return array
 *   An associative array with translatable strings.
 */
function mail_account_strings($name, $pass) {
  return array(
    'username' => t('Username: !mail', array('!mail' => $name)),
    'password' => t('Password: !pass', array('!pass' => $pass)),
  );
}
```

The `mail_account_strings()` function always must return an array with two keys: "username" and "password". The value of each key - should be a string returned by `t()` function. Text of string can be any, but it definitely should have the placeholder that can be replaced by one of the credentials in `hook_mail()` and by regexp - in Behat method.

Example of `hook_mail()`:
```php
/**
 * Implements hook_mail().
 */
function hook_mail($key, &$message, $params) {
  switch ($key) {
    case 'account':
      $message['subject'] = t('User account');
      $message['body'][] = t('You can login on the site using next credentials:');
      $message['body'] += mail_account_strings($params['mail'], $params['pass']);
      break;
  }
}
```

