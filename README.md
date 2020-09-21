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

## ⚠️ Breaking change in version 2 ⚠️

Module blueprints and templates now live in a separate `site/modules` folder.

## Instructions

Add a `modules` section to any page blueprint and a modules container will be automatically created.
 
You can create modules by putting them in a `site/modules` folder. For example you can add a `site/modules/text` folder with the template `text.php` and the blueprint `text.yml`.

In the parent page template you can then use `<?php $page->renderModules() ?>` to render the modules.

### Parent Page

#### `site/blueprints/pages/default.yml`

```yml
title: Default Page
sections:
  modules: true
```

#### `site/templates/default.php`

```php
<?php $page->renderModules() ?>
```

### Example Module

#### `site/modules/text/text.yml`

```yml
title: Text Module
fields:
  textarea: true
```

#### `site/modules/text/text.php`

```php
<h1><?= $module->title() ?></h1>
<?= $module->text()->kt() ?>
```

## Options

### Default Module Blueprint

By default, the `module.text` blueprint will be the first option when adding a module.
You can set it to another blueprint in your `site/config/config.php`:

```php
return [
  'medienbaecker.modules.default' => 'module.text'
];
```

### Autopublish Modules

You can turn on automatic publishing for modules in your `site/config/config.php`:

```php
return [
  'medienbaecker.modules.autopublish' => true
];
```

### Custom Module Model

This plugin creates a `ModulePage` model, overwriting certain methods.
You can extend this model with your own model:

```php
// site/config/config.php

return [
  'medienbaecker.modules.model' => 'CustomModulePage'
];
```

```php
// site/models/module.php

class CustomModulePage extends ModulePage {
  // methods...
}
```