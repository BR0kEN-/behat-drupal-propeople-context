### Work with Drupal users

Scenarios, which will use steps for testing WYSIWYG editors, should be tagged
with `@user` tag.

```gherkin
@wysiwyg
Scenario: Login as a user with filled fields
  Given I am logged in as a user with "administrator" role and filled fields:
    | Full name | Sergey Bondarenko   |
    | Position  | Developer           |
    | Company   | TestCompany         |
```

**IMPORTANT**: To use steps from this context you should correctly configure your Behat.

Example of `behat.yml`:
```yml
default:
  suites:
    default:
      contexts:
        - FeatureContext: ~
        - Behat\Drupal\Propeople\PropeopleContext: ~
        - Behat\Drupal\Propeople\User\UserContext: ~
  extensions:
```
