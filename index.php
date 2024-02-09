<?php

use Kirby\Cms\Template;

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
        $moduleTemplate = new Template($module->intendedTemplate());
        echo $moduleTemplate->render([
          'page' => $this,
          'module' => $module,
          'site' => $this->site(),
          ...$params
        ]);
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
      if ($rawModules = $this->find('modules')) {
        foreach ($rawModules->childrenAndDrafts() as $module) {
          if (!$module->isListed() && !$module->isDraft()) continue;
          if ($module->isDraft && !$module->isVerified(get('token'))) continue;
          $modules->append($module);
        }
      }
      return $modules;
    },
    'isModule' => function () {
      return Str::startsWith($this->intendedTemplate(), 'module.');
    },
    'uniqueModuleSlug' => function() {
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

        $blueprintFile  = $kirby->root('site') . '/modules/' . $name . '/'. $name . '.yml';
        $snippetFile = $kirby->root('site') . '/modules/' . $name . '/'. $name . '.php';

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
