<?php

use Kirby\Cms\File;
use Kirby\Cms\Page;
use Medienbaecker\Modules\ModulesCollection;

return [

  'renderModules' => function (string|array $containerOrParams = 'modules', array $params = []) {
    [$container, $params] = is_array($containerOrParams)
      ? ['modules', $containerOrParams]
      : [$containerOrParams, $params];

    foreach ($this->modules($container) as $module) {
      echo $module->renderModule($params);
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

    $language = kirby()->defaultLanguage()?->code();
    foreach ($modulesContainer->children() as $module) {
      if ($module->content($language)->hidden()->toBool() && $module->slug() !== $previewSlug) continue;
      $modules->append($module);
    }
    return $modules;
  },

  'isModule' => function () {
    return str_starts_with($this->intendedTemplate()->name(), 'module.');
  },

  'filePool' => function () {
    $owner = $this instanceof File ? $this->parent() : $this;
    if ($owner instanceof Page && $owner->isModule()) {
      return $owner->parent()?->parent()?->files() ?? $owner->files();
    }
    return $owner->files();
  }
];
