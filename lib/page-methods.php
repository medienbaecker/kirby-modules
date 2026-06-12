<?php

use Kirby\Cms\File;
use Kirby\Cms\Page;
use Medienbaecker\Modules\ModuleRegistry;
use Medienbaecker\Modules\ModuleSectionRoutes;
use Medienbaecker\Modules\ModulesCollection;

return [

  // Creates a real, persisted module (unlike Module::factory, which is
  // render-only). The container is created when missing, same as in the Panel.
  'createModule' => function (array $props, string $container = 'modules') {
    $modulesContainer = $this->find($container)
      ?? ModuleSectionRoutes::createContainer($this, $container);

    $template = ModuleRegistry::template($props['type'] ?? $props['template'] ?? null);

    // createChild always creates a draft; the blueprint's create.status
    // is Panel-only, so list the module explicitly.
    $module = kirby()->impersonate('kirby', fn() => $modulesContainer->createChild([
      'slug'     => $props['slug'] ?? ModuleRegistry::generateSlug($modulesContainer->id(), $template),
      'template' => $template,
      'content'  => $props['content'] ?? [],
    ])->changeStatus('listed'));

    return ModuleSectionRoutes::applyAutopublish($module);
  },

  'renderModules' => function (string|array $containerOrParams = 'modules', array $params = []) {
    [$container, $params] = is_array($containerOrParams)
      ? ['modules', $containerOrParams]
      : [$containerOrParams, $params];

    foreach ($this->modules($container) as $module) {
      $module->renderModule($params);
    }
  },

  'hasModules' => function () {
    foreach ($this->blueprint()->sections() as $section) {
      if ($section->type() === 'modules') return true;
    }
    return false;
  },

  // Can't use Page::renderVersionFromRequest()'s token check — it grants
  // token-less access to any listed page, but hidden modules are listed
  // too and must stay unaddressable without proof of access.
  'modules' => function (string $container = 'modules') {
    $modules = new ModulesCollection;
    $modulesContainer = $this->find($container);
    if (!$modulesContainer) return $modules;

    $previewSlug = get('_module');
    if ($previewSlug) {
      $module = $modulesContainer->find($previewSlug);
      $token = (string) get('_token');
      $tokenValid = $token !== '' && $module !== null && (
        hash_equals($module->version('latest')->previewToken(), $token) ||
        hash_equals($module->version('changes')->previewToken(), $token)
      );
      if (!kirby()->user() && !$tokenValid) $previewSlug = null;
    }

    foreach ($modulesContainer->children() as $module) {
      if ($module->isHidden() && $module->slug() !== $previewSlug) continue;
      $modules->append($module);
    }
    return $modules;
  },

  'isModule' => function () {
    return str_starts_with($this->intendedTemplate()->name(), 'module.');
  },

  // The hidden page holding a section's modules as children.
  'isModuleContainer' => function () {
    return $this->intendedTemplate()->name() === 'modules';
  },

  // Hidden state lives on the default language only — content(null) would
  // read the current Panel language instead.
  'isHidden' => function () {
    $language = kirby()->defaultLanguage()?->code();
    return $this->content($language)->hidden()->toBool();
  },

  'filePool' => function () {
    $owner = $this instanceof File ? $this->parent() : $this;
    if ($owner instanceof Page && $owner->isModule()) {
      return $owner->parent()?->parent()?->files() ?? $owner->files();
    }
    return $owner->files();
  }
];
