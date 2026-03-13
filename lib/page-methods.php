<?php

use Kirby\Cms\Pages;
use Kirby\Toolkit\Str;
use Medienbaecker\Modules\ModulesCollection;

return [
  'renderModules' => function (array $params = []) {
    foreach ($this->modules() as $module) {
      echo $module->renderModule($params);
    }
  },
  'hasModules' => function () {
    $modules = array_filter($this->blueprint()->sections(), function ($section) {
      return 'modules' === $section->type();
    });
    return count($modules) > 0;
  },
  'modules' => function () {
    $modules = new ModulesCollection;
    if ($modulesContainer = $this->find('modules')) {
      foreach ($modulesContainer->childrenAndDrafts() as $module) {
        if ($module->isUnlisted()) continue;
        if ($module->isDraft() && $module->renderVersionFromRequest() === null) continue;
        $modules->append($module);
      }
    }
    return $modules;
  },
  'isModule' => function () {
    return Str::startsWith($this->intendedTemplate(), 'module.');
  },
  'uniqueModuleSlug' => function () {
    $slug = $this->title()->slug();
    $siblings = $this->parent()?->parent()?->childrenAndDrafts() ?? new Pages();

    if ($siblings->filterBy('slug', $slug)->count() === 0) {
      return $slug;
    }

    $i = 2;
    while ($siblings->filterBy('slug', $slug . '-' . $i)->count() > 0) {
      $i++;
    }

    return $slug . '-' . $i;
  },
  'uniqueModuleTitle' => function () {
    $title = $this->blueprint()->title();
    $siblings = $this->parent()?->childrenAndDrafts() ?? new Pages();
    $count = $siblings->filterBy('intendedTemplate', $this->intendedTemplate()->name())->count();
    return $count > 0 ? $title . ' ' . ($count + 1) : $title;
  }
];
