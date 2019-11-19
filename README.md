# Kirby Modules

Easily build modular Kirby websites.

![module](https://user-images.githubusercontent.com/7975568/68720738-8a3f2b00-05b0-11ea-829f-ec6842312d2b.gif)

## Introduction to modules

### What is a module?

A module is a regular page, differentiated from other pages by being inside a modules container.
This approach makes it possible to use pages as modules without sacrificing regular subpages:

```
📄 Page
  📄 Subpage A
  📄 Subpage B
  🗂 Modules
    📄 Module A
    📄 Module B
```

### How do I set up the templates/blueprints?

This plugin assumes you're going to use a `module.` prefix for your module blueprints. For example `site/pages/module.text.yml`. In there you can do anything you want—it's just a regular page.

On the modular (mother) pages you iterate over the modules inside the modules container like this:

```php
<?php
  foreach($page->find("modules")->children()->listed() as $module) {
    snippet('modules/' . $module->intendedTemplate(), ['page' => $module])
  } 
?>
```

As you can see, the modules don't have templates and simply live in for example `site/snippets/modules/module.text.php` or `site/snippets/modules/module.images.php`.

## Features of this plugin

### Modules section

The modules section adds some features to the core pages section: 

1. Every blueprint starting with `module.` is available to create.
2. The module blueprint title is displayed in the info.
3. The parent is set to the modules container.
4. The link to the modules container in the headline is disabled.
5. Small style adjustments to differentiate it from pages.

Using the modules section on a page will automatically trigger a built-in hook that creates the `modules` container page.

```yml
sections:
  modules:
    type: modules
```

### Module Model (sorry, that's what it's called)

To overwrite the module URL you'd have to add a page model for every single module.
This plugin overwrites the URL of every module page from `example.com/subpage-a/modules/text` to `example.com/subpage-a#text`.

### Module blueprint

By adding `extends: module/changeTemplate` to the options of your module blueprints, every blueprint starting with `module.` is available to change to in the page options.

```yml
options:
  extends: module/changeTemplate
```

## Options

By default, the `module.text` blueprint will be the first option when adding a module. You can set it to another blueprint in your `site/config/config.php`:

```php
return [
  'medienbaecker.modules' => [
      'default' => 'module.text'
  ]
];
```
