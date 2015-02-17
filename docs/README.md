# Writing the tests

Each `*.feature` file should start from heading:

```gherkin
@d7 @api @javascript
Feature: Example
```

The files for testing (images, documents, videos etc.) should be stored in [/behat/resources](resources) folder.

## Examples

- [Upload an image (Media module)](UPLOAD_MEDIA.md)
- [Testing emails](EMAILS.md)

## Steps

```gherkin
Given I switch to an iframe "mediaBrowser"
```

```gherkin
When I switch back from an iframe
```

```gherkin
Then I should see the thumbnail
```

```gherkin
And I should see no errors
```

```gherkin
Given I should use the "1280x800" screen resolution
```

```gherkin
When I press ".link" element
```

```gherkin
Then I fill in "field_company[und][0]" with value of field "user_company" of current user
```

```gherkin
Given I typed "Joe" in the "name" field and choose 2 option from autocomplete variants
```

```gherkin
Then I wait until AJAX is finished
```

```gherkin
Given I [un]check the boxes:
  | -Consumer Products  |
  | -ICT                |
  | -Financial Services |
```

```gherkin
Given I check the "Show" radio button
```

```gherkin
Then I wait 60 seconds
```

```gherkin
## You should use @emails tag for scenario with this step.
Then I check that email to "test@propeople.com.ua" was sent
```

```gherkin
## You should use @emails tag for scenario with this step.
Then I check that email body contains the "Congratulations" text
```

```gherkin
## To use this step you should correctly configure your Behat.
Then I login with user credentials that was sent via email
```

To see all, available in your system, steps execute the `behat -dl`.
