<?php

use Kirby\Cms\Pages;
use Kirby\Toolkit\Str;
use Medienbaecker\Modules\ModulesCollection;

return [
  'renderModules' => function (string|array $containerOrParams = 'modules', array $params = []) {
    if (is_array($containerOrParams)) {
      $params = $containerOrParams;
      $container = 'modules';
    } else {
      $container = $containerOrParams;
    }
    foreach ($this->modules($container) as $module) {
      echo $module->renderModule($params);
    }
  },
  'hasModules' => function () {
    $modules = array_filter($this->blueprint()->sections(), function ($section) {
      return 'modules' === $section->type();
    });
    return count($modules) > 0;
  },
  'modules' => function (string $container = 'modules') {
    $modules = new ModulesCollection;
    if ($modulesContainer = $this->find($container)) {
      $previewSlug = get('_module');
      if ($previewSlug) {
        $draft = $modulesContainer->draft($previewSlug);
        $hasAccess = kirby()->user() || ($draft && $draft->renderVersionFromRequest() !== null);
        if (!$hasAccess) $previewSlug = null;
      }
      $children = $previewSlug
        ? $modulesContainer->childrenAndDrafts()->filter(
            fn($child) => !$child->isUnlisted()
          )->sortBy(function ($child) {
            if ($child->isDraft()) {
              $sort = (float) $child->content()->moduleSort()->value();
              return $sort ?: PHP_FLOAT_MAX;
            }
            return (float) ($child->num() ?? PHP_INT_MAX);
          }, 'asc')
        : $modulesContainer->children()->listed();

      foreach ($children as $module) {
        if ($module->isDraft() && $previewSlug !== $module->slug()) continue;
        $modules->append($module);
      }
    }
    return $modules;
  },
  'isModule' => function () {
    return Str::startsWith($this->intendedTemplate(), 'module.');
  },
  'uniqueModuleTitle' => function () {
    $title = $this->blueprint()->title();
    $siblings = $this->parent()?->childrenAndDrafts() ?? new Pages();
    $count = $siblings->filterBy('intendedTemplate', $this->intendedTemplate()->name())->count();
    return $count > 0 ? $title . ' ' . ($count + 1) : $title;
  }
];
