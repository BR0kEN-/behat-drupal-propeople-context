### Upload an image with help of the Media module.

File **logo.jpg** must be located in `resources` directory.

#### Examples

```gherkin
Given I click "Select media"
Then I switch to an iframe "mediaBrowser"
And I attach the file "logo.jpg" to "edit-upload"
And I press "Submit"
Then I switch back from an iframe
And I should see the thumbnail
And I should see "Remove media"
```
