# Writing the tests

Each `*.feature` file should start from heading:

```gherkin
@d7 @api @javascript
Feature: Example
```

The files for testing (images, documents, videos etc.) should be stored in [resources](/behat/resources) folder.

## Examples

- [Upload an image (Media module)](MEDIA.md)
- [Testing emails](EMAIL.md)
- [Testing WYSIWYG](WYSIWYG.md)

## Raw Methods

In your `FeatureContext` you can get another contexts and use their steps. See
on [RawPropeopleContext](/src/Behat/Drupal/Propeople/RawPropeopleContext.php)
for more information.

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
## The text, label, name or CSS selector can be used as selector.
When I press ".link" element
```

```gherkin
## Unstable method.
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
## - The button can be found by ID, name or CSS selector.
## - The label of radio button can be specified inaccurately.
## - If element has more than one label and one of them is hidden, then
##   will used only visible, if exist.
## - If trying to get the field by label, then it must have the "for" attribute
##   and element with ID, specified in that attribute, must exist.
Given I check the "Show" radio button
```

```gherkin
Then I wait 60 seconds
```

```gherkin
## Region can be found by CSS selector or name from "region_map" parameter
## of "behat.yml".
Then I work with elements in "header" region
```

```gherkin
## This method must be used instead of 'I fill in "field" with "value"'.
Then I fill "last_name" with "Bondarenko"
```

```gherkin
## This method must be used instead of 'I fill in the following:'.
Then I fill the following:
  | first_name | Sergey    |
  | last_name | Bondarenko |
```

```gherkin
## - Region can be found by CSS selector or name from "region_map" parameter
##   of "behat.yml".
## - The text can be specified inaccurately, but you should remember that in
##   region can be more than one element with specified text and will pressed
##   the first only.
Then I press on element with text "Account" in "footer" region
```

### Redirect context

All scenarios, used steps from this context, should be tagged with `@redirect` tag.

```gherkin
## Waits for only one redirect and goes to the next step.
Then I should be redirected
```

```gherkin
## Waits as long as URL of the page will not be the same as specified.
## - The URL can be relative or absolute.
## - By default, the waiting timeout is set to 15 seconds, but you can change
##   this in "behat.yml".
Then I should be redirected on "https://example.com"
```

### Email context

All scenarios, used steps from this context, should be tagged with `@email` tag.

```gherkin
Then I check that email to "test@propeople.com.ua" was sent
```

```gherkin
Then I check that email body contains the "Congratulations" text
```

```gherkin
## To use this step you should correctly configure your Behat.
Then I login with user credentials that was sent via email
```

### Drush context

All scenarios, used steps from this context, should be tagged with `@drush` tag.

```gherkin
Then I login with one time link
```

### WYSIWYG context

All scenarios, used steps from this context, should be tagged with `@wysiwyg`
tag. Also, any WYSIWYG editor can be found by usual selector of form field.

**Note**: only CKEditor supported for now, but, in future we're planing to provide TinyMCE
support.

```gherkin
## If this step was used, then you no need to specify selector for next steps
## from this context while working with only one editor.
Given I work with "Presentation" WYSIWYG editor
```

```gherkin
Then I fill "<strong>Text</strong>" in "Presentation" WYSIWYG editor
```

```gherkin
Then I type "<p>additional text</p>" in "Presentation" WYSIWYG editor
```

```gherkin
Then I should see "Text" in "Presentation" WYSIWYG editor
```

```gherkin
Then I should not see "vulnerability" in "Presentation" WYSIWYG editor
```

To see all, available in your system, steps execute the `behat -dl`.
