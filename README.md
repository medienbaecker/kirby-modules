![modules](https://user-images.githubusercontent.com/7975568/93625345-147ec980-f9e2-11ea-8cbd-3cd640829ddc.png)

## What's a Module?

A module is a regular page, differentiated from other pages by being inside a modules container.
This approach makes it possible to use pages as modules without sacrificing regular subpages.

```
📄 Page
  📄 Subpage A
  📄 Subpage B
  🗂 Modules
    📄 Module A
    📄 Module B
```

## Instructions

Add a `modules` section to any page blueprint and a modules container will be automatically created.
 
You can create modules by putting them in a `site/modules` folder. For example you can add a `site/modules/text` folder with the template `text.php` and the blueprint `text.yml`.

In the parent page template you can then use `<?php $page->renderModules() ?>` to render the modules.

### Parent Page

#### `site/templates/default.php`

```php
<?php $page->renderModules() ?>
```

#### `site/blueprints/pages/default.yml`

```yml
title: Default Page
sections:
  modules: true
```

### Example Module

#### `site/modules/text/text.php`

```php
<h1><?= $module->title() ?></h1>
<?= $module->text()->kt() ?>
```

#### `site/modules/text/text.yml`

```yml
title: Text Module
fields:
  textarea: true
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
