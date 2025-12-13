# Kirby Modules

This plugin makes it super easy to create modular websites with Kirby.

> [!WARNING]  
> Using Kirby 4, you'll have to stick to version [2.8.4](https://github.com/medienbaecker/kirby-modules/releases/tag/2.8.4).

## Features

📦 **Module Creation**
- 🗂️ Create modules in `site/blueprints/modules/[module].yml` and `site/snippets/modules/[module].php`
- 🔧 Use the `make:module` CLI command to generate new modules

🧩 **Core Functionality**
- 🔄 Automatically creates a hidden modules storage page for pages with a modules section
- 🎨 Keeps `changeTemplate` options up to date
- 🚚 Allows moving modules to other modules storage pages
- 🧭 Sets the `navigation` option so you can use the arrows to move between modules
- 📄 Easily render modules with `<?= $page->modules() ?>`
- 🧰 Useful methods like `hasModules()`, `isModule()` and `moduleId()`
- 🏷️ Optionally auto-generate unique slugs for modules
- 👁️ View draft modules on parent pages via the panel preview button
- 🔗 Extended `url()` method with anchor links on the parent page
- 🚦 Accessing Module URLs directly redirects to the parent page with an anchor

⚙️ **Customization Options**
- 🎛️ Set a default module type
- 🚫 Exclude specific module types
- 🚀 Option to auto-publish modules
- 🔀 Control redirect behavior after module creation

<img src="https://github.com/medienbaecker/kirby-modules/assets/7975568/1bece4a6-9ba3-4e8a-be67-7053876f71ba" alt="Preview" width="500px">

## Installation

Download this repository to `/site/plugins/kirby-modules`.

Alternatively, you can install it with composer: `composer require medienbaecker/kirby-modules`

## Quick Start

1. Install the plugin
2. Create a module blueprint in `site/blueprints/modules/[module].yml` and a snippet in `site/snippets/modules/[module].php`
3. Add a `modules` section to a page blueprint
4. Render the modules in your template with `<?= $page->modules() ?>`

I created an [example repository](https://github.com/medienbaecker/modules-example) with Kirby's plainkit, this plugin and three very simple modules.

## Usage

### What's a Module?

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

### Creating Modules

Similar to [blocks](https://getkirby.com/docs/reference/panel/fields/blocks), you can create module blueprints in `site/blueprints/modules/` and module snippets in `site/snippets/modules/`. E.g. `site/blueprints/modules/text.yml` and `site/snippets/modules/text.php`.

### Adding Modules to Pages

Add a `modules` section to any page blueprint and a modules container will be automatically created.

### Rendering Modules

In the template you can use `<?= $page->modules() ?>` to render the modules.

### Example

#### `site/blueprints/pages/default.yml`

```yml
title: Default Page
sections:
  modules: true
```

#### `site/templates/default.php`

```php
<?= $page->modules() ?>
```

#### `site/blueprints/modules/text.yml`

```yml
title: Text Module
fields:
  textarea: true
```

#### `site/snippets/modules/text.php`

```php
<div class="<?= $module->moduleId() ?>" id="<?= $module->uid() ?>">
  <h1><?= $module->title() ?></h1>
  <?= $module->textarea()->kt() ?>
</div>
```

You can access the module page object with `$module` and the parent page object with `$page`.
The `$module->moduleId()` method returns the module ID as a BEM class, e.g. `module--text` or `module--gallery`.

## Configuration

The following options are available to add to your `site/config/config.php`:

### Default Module Blueprint

```php
return [
  'medienbaecker.modules.default' => 'gallery' // default: 'text'
];
```

### Exclude Module Blueprints

```php
return [
  'medienbaecker.modules.exclude' => [
    'hero',
    'anotherForbiddenModule'
  ]
];
```

### Automatically generate slug

```php
return [
  'medienbaecker.modules.autoslug' => true
];
```

### Autopublish Modules

```php
return [
  'medienbaecker.modules.autopublish' => true
];
```

### Enable redirect

```php
return [
  'medienbaecker.modules.redirect' => true
];
```

## Customization

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

### Manually define available modules

By default, this plugin automatically populates the `templates` option of the modules section with all modules. If you want to manually define the available modules, you can do so in your blueprint:

```yml
modules:
  templates:
    - module.text
    - module.images
```

This restricts which modules can be created AND moved into this section.

## License

This project is licensed under the terms of the MIT license.
