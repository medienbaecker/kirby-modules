<?php

use Kirby\Toolkit\Str;
use Kirby\Toolkit\I18n;
use Kirby\Cms\Section;

$blueprints = [];
foreach ($moduleRegistry['blueprints'] as $blueprint => $file) {
  if (Str::startsWith($blueprint, 'pages/module.')) {
    $blueprints[] = str_replace('pages/', '', $blueprint);
  }
}

// Move default module to the top
$defaultModule = array_search('module.' . option('medienbaecker.modules.default', 'text'), $blueprints);
if ($defaultModule !== false) {
  $defaultBlueprint = $blueprints[$defaultModule];
  unset($blueprints[$defaultModule]);
  array_unshift($blueprints, $defaultBlueprint);
}

// Exclude modules
$excludedModules = option('medienbaecker.modules.exclude', []);
if (!empty($excludedModules)) {

  foreach($excludedModules as $excludedModule) {
    if (in_array('module.' . $excludedModule, $blueprints)) {
      $key = array_search('module.' . $excludedModule, $blueprints);
      unset($blueprints[$key]);
    }
  }
}

$base = Section::$types['pages'];

if (is_string($base)) {
  $base = include $base;
}

return array_replace_recursive($base, [
  'props' => [
    'create' => function ($create = null) use ($blueprints) {
      return $create ?? $blueprints;
    },
    'empty' => function ($empty = null) {
      return $empty ?? I18n::translate('modules.empty');
    },
    'headline' => function ($headline = null) {
      return $headline ?? I18n::translate('modules');
    },
    'info' => function (string $info = '{{ page.moduleName }}') {
      return $info;
    },
    'image' => false,
    'parent' => function ($parent = null) {
      $class = get_class($this->model()) === 'Kirby\Cms\Site' ? 'site' : 'page';
      return $this->model()->find('modules') ? $class . '.find("modules")' : $parent;
    },
    'layout' => function (string $layout = 'list') {
      $layouts = ['list', 'cardlets', 'cards', 'table', 'module'];
      return in_array($layout, $layouts) ? $layout : 'list';
    }
  ]
]);
