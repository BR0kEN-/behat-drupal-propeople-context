# Behat context by Propeople

The Propeople Drupal Context provide a flexible methods for testing websites,
running on Drupal 7. The contexts in this repository extend the contexts of
[DrupalExtension](https://github.com/jhedstrom/drupalextension).

[![Build Status](https://scrutinizer-ci.com/g/BR0kEN-/behat-drupal-propeople-context/badges/build.png?b=master)](https://scrutinizer-ci.com/g/BR0kEN-/behat-drupal-propeople-context/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/BR0kEN-/behat-drupal-propeople-context/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/BR0kEN-/behat-drupal-propeople-context/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/behat/drupal-propeople-context/v/stable.svg)](https://packagist.org/packages/behat/drupal-propeople-context)
[![Total Downloads](https://poser.pugx.org/behat/drupal-propeople-context/downloads.svg)](https://packagist.org/packages/behat/drupal-propeople-context)
[![Latest Unstable Version](https://poser.pugx.org/behat/drupal-propeople-context/v/unstable.svg)](https://packagist.org/packages/behat/drupal-propeople-context)
[![License](https://poser.pugx.org/behat/drupal-propeople-context/license.svg)](https://packagist.org/packages/behat/drupal-propeople-context)

## Installation

- Install [Composer](https://getcomposer.org/doc/00-intro.md).
- Navigate to folder with your Drupal project. Would be better if a project has the similar structure:
```
/project_name
|-- docroot
|   |-- [drupal installation without any custom files]
|   |-- [...]
|-- [another folders and files e.g. tests, scripts etc.]
|-- [...]
```
- Create the `composer.json` file:
```json
{
  "require": {
    "behat/drupal-propeople-context": "~1.2"
  },
  "config": {
    "bin-dir": "bin/"
  },
  "scripts": {
    "post-install-cmd": "mv bin/bdpc bin/behat"
  }
}
```
- Execute the `composer install` command.
- Initialize the basic context and configuration by executing the
  `bin/behat --init --url=http://example.com`. Also, command can take the `dir`
  parameter if the Drupal installation located above current folder.
  For example `bin/behat --init --url=http://project.loc --dir=docroot`.
- Configure the `behat.yml` if needed.
- Read the [documentation](docs) about creating the features.

## We are using

- [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) for autoloading.
- [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md) for code style.

## Author

- [Sergey Bondarenko (BR0kEN)](https://github.com/BR0kEN-)

## Contributors

- [Alexander Petrov](https://github.com/aapetrov)
- [Anastasia Guba](https://github.com/Naastya)
