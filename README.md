# Kirby Modules

Easily build modular Kirby websites.

## What is a module?

A module is a regular page, differentiated from other pages by being inside a modules container. This approach makes it possible to use pages as modules without sacrificing regular subpages:

```
📄 Page
  📄 Subpage A
  📄 Subpage B
  🗂 Modules
    📄 Module A
    📄 Module B
```

## Modules section

The modules section adds some features to the core pages section: 

1. Every blueprint starting with `module.` is available to create.
2. The module blueprint title is displayed in the info.
3. The parent is set to the modules container.
4. The link to the modules container in the headline is disabled.

```yml
sections:
  modules:
    type: modules
```

## Module blueprint

By adding `extends: modulehelper/changeTemplate` to the options of your module blueprints, every blueprint starting with `module.` is available to change to.

```yml
# Module blueprints
options:
  extends: modulehelper/changeTemplate
```