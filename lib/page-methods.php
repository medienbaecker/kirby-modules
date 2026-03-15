<?php

use Kirby\Cms\Pages;
use Kirby\Toolkit\Str;
use Medienbaecker\Modules\ModulesCollection;

return [

  // Echo all modules as HTML
  // Accepts container name (string) and/or snippet params (array)
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

  // Check if this page's blueprint has a modules section
  'hasModules' => function () {
    $modules = array_filter($this->blueprint()->sections(), function ($section) {
      return $section->type() === 'modules';
    });
    return count($modules) > 0;
  },

  // Return a ModulesCollection for the given container
  // Supports draft preview via ?_module=slug (requires auth or valid token)
  'modules' => function (string $container = 'modules') {
    $modules = new ModulesCollection;
    if ($modulesContainer = $this->find($container)) {
      $previewSlug = get('_module');
      if ($previewSlug) {
        $draft = $modulesContainer->draft($previewSlug);
        $hasAccess = kirby()->user() || ($draft && $draft->renderVersionFromRequest() !== null);
        if (!$hasAccess) $previewSlug = null;
      }
      // Preview mode: include drafts with moduleSort ordering
      // Normal mode: just listed children
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
        // Only include the specific previewed draft, not all drafts
        if ($module->isDraft() && $previewSlug !== $module->slug()) continue;
        $modules->append($module);
      }
    }
    return $modules;
  },

  // True if the page's template starts with 'module.'
  'isModule' => function () {
    return Str::startsWith($this->intendedTemplate(), 'module.');
  },

  // Generate collision-free title: "Text", "Text 2", "Text 3"
  // Called via blueprint query: {{ page.uniqueModuleTitle }}
  'uniqueModuleTitle' => function () {
    $title = $this->blueprint()->title();
    $siblings = $this->parent()?->childrenAndDrafts() ?? new Pages();
    $count = $siblings->filterBy('intendedTemplate', $this->intendedTemplate()->name())->count();
    return $count > 0 ? $title . ' ' . ($count + 1) : $title;
  }
];
