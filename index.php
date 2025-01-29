<?php

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Pages;
use Kirby\Template\Template;
use Kirby\Toolkit\Str;

include __DIR__ . '/lib/models.php';
include __DIR__ . '/lib/functions.php';
include __DIR__ . '/lib/collection.php';

$moduleRegistry = createModuleRegistry();

Kirby::plugin('medienbaecker/modules', [
  'templates' => $moduleRegistry['templates'],
  'pageModels' => $moduleRegistry['pageModels'],
  'blueprints' => $moduleRegistry['blueprints'],
  'sections' => [
    'modules' => include __DIR__ . '/lib/sections/modules.php'
  ],
  'fields' => [
    'modules_redirect' => include __DIR__ . '/lib/fields/redirect.php'
  ],
  'pageMethods' => [
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
        foreach ($modulesContainer->children()->listed() as $module) {
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

      // if the slug is already unique, return it
      if ($siblings->filterBy('slug', $slug)->count() === 0) {
        return $slug;
      }

      // if the slug is not unique, add a number
      $i = 2;
      while ($siblings->filterBy('slug', $slug . '-' . $i)->count() > 0) {
        $i++;
      }

      return $slug . '-' . $i;
    }
  ],
  'api' => [
    'routes' => include __DIR__ . '/lib/routes.php',
  ],
  'translations' => include __DIR__ . '/lib/translations.php',
  'commands' => [
    'make:module' => [
      'description' => 'Creates a new module',
      'args' => [
        'name' => [
          'description' => 'The name of the module',
        ]
      ],
      'command' => static function ($cli): void {
        $kirby = $cli->kirby();
        $name  = $cli->argOrPrompt('name', 'Enter a name for the module:');

        $blueprintFile  = $kirby->root('blueprints') . '/modules/' . $name . '.yml';
        $snippetFile = $kirby->root('snippets') . '/modules/' . $name . '.php';

        $cli->make($blueprintFile, 'title: {{ title }}', [
          'title' => ucfirst($name)
        ]);

        $cli->make($snippetFile, '<!-- {{ title }} -->', [
          'title' => ucfirst($name)
        ]);

        $cli->success('The module has been created');
      }
    ]
  ]
]);
