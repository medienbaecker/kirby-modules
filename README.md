# Kirby Modulehelper

This simple plugin helps keeping the blueprints of modular Kirby websites clean.

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