# Kirby Modules

Modular page building for [Kirby](https://getkirby.com/) using regular Kirby pages with their own blueprint and snippet, edited inline on the parent page.

<img width="1988" height="1452" alt="Screenshot of the modules section with two modules, a text module with a textarea and a text with buttons module with both a textarea and a structure field for buttons" src=".github/screenshot.webp" />

- Edit module fields inline on the parent page with a blocks-like UI
- Signed previews for hidden modules
- Great performance with large numbers of modules
- Robust multilanguage behaviour
- Automatic container page creation, separating modules from regular subpages
- Multiple modules sections per page
- Sensible defaults in module blueprints

## Installation

```
composer require medienbaecker/kirby-modules
```

Or download this repository and put it into `site/plugins/modules`.

## Quick Start

> Prefer to see everything wired up? The [moduleskit](https://github.com/medienbaecker/modules-example) is a small, ready-to-run example site.

**1. Add a modules section** to a page blueprint:

```yml
# site/blueprints/pages/default.yml
title: Default Page
sections:
  modules:
    type: modules
```

**2. Create a module type** with a blueprint for its fields and a snippet for its HTML:

```yml
# site/blueprints/modules/text.yml
title: Text
fields:
  textarea:
    label: Text
```

```php
// site/snippets/modules/text.php
<div id="<?= $module->slug() ?>">
  <?= $module->textarea()->kt() ?>
</div>
```

Or create both files in one go using the built-in [CLI](https://github.com/getkirby/cli) command:

```bash
kirby make:module text
```

**3. Render the modules** in your template:

```php
// site/templates/default.php
<?= $page->modules() ?>
```

## How It Works

A module is a regular page, differentiated from other pages by being inside a modules container. This makes it possible to use pages as modules without sacrificing regular subpages:

```
Page
├── Subpage A
├── Subpage B
└── Modules
    ├── Module A
    └── Module B
```

The container is created automatically and stays out of the way. Editors only ever see the modules section on the parent page. Because modules are pages, everything you know about pages applies: module blueprints support the full Kirby blueprint layout, including [columns](https://getkirby.com/docs/guide/blueprints/layout#columns) and [sections](https://getkirby.com/docs/guide/blueprints/layout#sections), and modules can have their own files, translations and models.

### Naming

A module type is defined by two files sharing one name:

- `site/blueprints/modules/text.yml` for the fields
- `site/snippets/modules/text.php` for the HTML

Internally, the module page's template gets a prefix: a `text` module is a page with the template `module.text`. You don't need to remember which form goes where as every plugin option (`templates`, `templatesIgnore`, `default`) accepts both `text` and `module.text`.

## Editing in the Panel

Modules are edited inline on the parent page: expand or collapse them, sort them by dragging or with the keyboard, and use the toolbar on each card to edit, preview, duplicate, add or delete.

### Visibility

Each module's visibility can be toggled with a single click on its card. Hidden modules stay in place, keeping their sort position and any inline edits, but the frontend skips over them when rendering. The card shows a striped background while a module is hidden.

New modules are created hidden, so editors can prepare content before it goes live. Set the [`autopublish` option](#config-options) to create them visible instead.

Every module card has a preview button. Visible modules link to the module's anchor on the live page. Hidden modules get a signed preview URL (token + `_module` query param) instead, so authors can verify them on the live URL without a Panel login.

### Anchors

A module's slug doubles as its anchor. Use it as the element ID in your snippet:

```php
<div id="<?= $module->slug() ?>">
```

The anchor is always visible on the module card (e.g. `#text`) and can be changed by clicking it, or via "Change anchor" in the toolbar's dropdown.

### Changing types

"Change type" in the toolbar's dropdown switches a module to another type. Fields keep their content when the new blueprint has a field with the same name and type.

### Preview images

Add preview images to make the create and change-type dialogs show a visual grid instead of a dropdown. Drop images into `assets/module-previews/`, named after the module, for example `text.png` for the `text` module. Any image format works; a 16:9 ratio looks best.

Types without a matching image fall back to their blueprint `icon`. If no type has a preview image, the dialogs keep the plain dropdown.

## Section Options

| Option            | Type     | Description                                    |
| ----------------- | -------- | ---------------------------------------------- |
| `default`         | `string` | Pre-selected module type in the create dialog  |
| `templates`       | `array`  | Manually define available types instead of all |
| `templatesIgnore` | `array`  | Hide specific module types                     |
| `min`             | `int`    | Minimum number of modules                      |
| `max`             | `int`    | Maximum number of modules                      |
| `empty`           | `string` | Empty state text                               |

Type names work with or without the `module.` prefix.

### Multiple sections

Each section's name (the YAML key) becomes its container's slug, so a page can have several independent module areas:

```yml
sections:
  modules:
    type: modules
    default: text
  sidebar:
    type: modules
    templates:
      - cta
      - newsletter
```

```php
// Default container for the section called `modules`
<?= $page->modules() ?>

// Secondary container for the section called `sidebar`
<?= $page->modules('sidebar') ?>
```

## Rendering

`$page->modules()` returns the visible modules as a collection; echoing it renders every module's snippet:

```php
<?= $page->modules() ?>
```

`renderModules()` does the same and can pass extra variables into every snippet:

```php
<?php $page->renderModules(['theme' => 'dark']) ?>

// or for a named container:
<?php $page->renderModules('sidebar', ['theme' => 'dark']) ?>
```

Inside a snippet, `$module` is the module page and `$page` is the parent page. Variables from [controllers](https://getkirby.com/docs/guide/templates/controllers) are also available.

Modules also work on the site itself: add a modules section to `site.yml` and use `$site->modules()` in your templates.

## Template Methods

| Method                          | Description                                                  |
| ------------------------------- | ------------------------------------------------------------ |
| `$page->modules()`              | All visible modules (default container)                      |
| `$page->modules('sidebar')`     | Modules from a named container                               |
| `$page->renderModules($params)` | Render all modules, optionally passing variables             |
| `$page->hasModules()`           | Page has a modules section                                   |
| `$page->isModule()`             | Page is a module                                             |
| `$module->isHidden()`           | Module is hidden (always reads the default language)         |
| `$page->filePool()`             | Files for blueprint queries (host page if module, else self) |
| `$module->moduleId()`           | CSS BEM class (e.g. `module--text`)                          |
| `$module->moduleName()`         | Blueprint title                                              |

## Advanced

### File pools

By default, a files field in a module sees only that module's own files. That's okay if you want to add a files section to the module, too. Most of the time, however, you want to use the (grand)parent page's file pool.

The `filePool` method resolves to the right files collection regardless of where it's called:

- On a **module**, returns the host page's files (the module's grandparent: the page that owns the modules container).
- On any other page, the page's own files.
- On the site, file, or user, that model's own files.

Use it as the `query` of any files field that should follow this rule:

```yml
type: files
query: model.filePool
uploads:
  parent: model.filePool.parent
```

To access the page of the file pool, you can use `model.filePool.parent`, as shown in the `uploads` option.

### Custom models

Override the model for _all_ module types via config:

```php
// site/config/config.php
'medienbaecker.modules.model' => CustomModulePage::class,
```

Or override a _single_ module type via `site/models/` (as you would with any regular page):

```php
// site/models/module.text.php
class ModuletextPage extends Medienbaecker\Modules\ModulePage {
  // your methods
}
```

### Virtual modules

`Module::factory()` mirrors Kirby's `Block::factory()`: it creates a module from code and renders it with its regular snippet.

```php
use Medienbaecker\Modules\Module;

echo Module::factory([
  'type' => 'text',
  'content' => [
    'textarea' => 'Hello from code'
  ]
])->toHtml();
```

`Modules::factory()` is the plural equivalent (like `Blocks::factory()`) and renders when echoed:

```php
use Medienbaecker\Modules\Modules;

echo Modules::factory([
  ['type' => 'text', 'content' => ['textarea' => 'One']],
  ['type' => 'text', 'content' => ['textarea' => 'Two']],
]);
```

Inside the snippets, `$module` works as usual and `$page` is the current page. Virtual modules are render-only: they don't appear in the Panel.

### Config options

```php
// site/config/config.php
return [
  // Create new modules visible instead of hidden (default: false)
  'medienbaecker.modules.autopublish' => true,

  // Override the page model for all module types
  'medienbaecker.modules.model' => CustomModulePage::class,
];
```

## Licensing

Kirby Modules is a commercial plugin. You can use it for free on local environments but using it in production requires a valid licence. You can pay what you want, the suggested price being 99€ per project. Feel free to choose "0" when working on a purposeful project ❤️

[Buy a licence](https://medienbaecker.com/plugins/modules)

## Credits

The visual type picker was inspired by [Juno](https://juno-hamburg.com)'s [Visual Block Selector](https://github.com/junohamburg/kirby-visual-block-selector).
