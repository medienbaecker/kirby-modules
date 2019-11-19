# Kirby Modules

Easily build modular Kirby websites.

![module](https://user-images.githubusercontent.com/7975568/69164144-ba765480-0aef-11ea-8b4e-b586066c3cbf.gif)

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

### Comparison to other approaches

You can also set up modular Kirby websites with structure fields or the [builder plugin](https://github.com/TimOetting/kirby-builder).

In comparison this approach offers some advantages:

- Different file pool for every module
- Better performance since nesting is better for file-based CMS
- Anchor links out of the box
- No nesting issues with certain fields/plugins

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

### Example installation

I put a simple example installation in a separate branch. Maybe this helps you getting started.

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

### Module Models (sorry, that's what it's called)

To overwrite the module URL you'd have to add a page model for every single module.
This plugin overwrites the URL of every module page from `example.com/subpage-a/modules/text` to `example.com/subpage-a#text`.

### Module Templates

This plugin adds templates with a redirection to the modules container and all the single modules. This way nobody can access the pages directly, not even by guessing the URLs.

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
