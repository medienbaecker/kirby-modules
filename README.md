# Kirby Modulehelper

This simple plugin helps keeping the blueprints of modular Kirby websites clean.

## Automatic blueprint lists

Instead of manually listing the module blueprints in multiple files, you can use the following code:

```yml
# Parent/container blueprints
sections:
  modules:
    type: pages
    parent: page.find("modules")
    extends: modulehelper/create
```

```yml
# Module blueprints
options:
  extends: modulehelper/changeTemplate
```

Every blueprint starting with `module.` will be available to create/change to.

## Useful page methods

The `moduleDisplayName()` page method can be used to display the module name in the info area of pages sections:

```yml
type: pages
info: "{{ page.moduleDisplayName }}"
```

The `moduleName()` page method can be used to return the module name as a class:

```html+php
<div class="module <?= $page->moduleName() ?>"></div>
```
