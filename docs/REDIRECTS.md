### Check redirects in testing

Scenarios, which will use steps for checking the redirect, should be tagged
with `@redirects` tag.

```gherkin
@redirects
Scenario: Form submission
  And I fill "First name" with "Testfirstname"
  When I press "Submit" element
  Then I should not see the heading "Error"
  And should not see text matching "The website encountered an unexpected error"
  And should see no errors
  Then I am at "https://login.salesforce.com/"
  And fill the following:
    | username | salesforce@email.com |
    | password | salesforcepassword   |
  When I press "Login" element
  Then I should be redirected on "https://emea.salesforce.com/home/home.jsp"
  When I click "Web Integrations"
  Then I should see text matching "Testfirstname"
```

**IMPORTANT**: For testing redirects you should correctly configure your Behat.

Example of `config.yml`:
```yml
default:
  suites:
    default:
      contexts:
        - FeatureContext: ~
#        - Behat\Drupal\Propeople\Redirect\RedirectContext: ~
#            wait_for_redirect: 30
        - Drupal\DrupalExtension\Context\MinkContext: ~
        - Drupal\DrupalExtension\Context\DrupalContext: ~
  extensions:
```
