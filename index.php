<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Pages;
use Kirby\Toolkit\Str;
use Medienbaecker\Modules\ModuleRegistry;
use Medienbaecker\Modules\ModuleCreateDialog;

include __DIR__ . '/lib/models.php';
include __DIR__ . '/lib/collection.php';

$moduleRegistry = ModuleRegistry::create();

Kirby::plugin('medienbaecker/modules', [
  'templates' => $moduleRegistry['templates'],
  'snippets' => $moduleRegistry['snippets'],
  'pageModels' => $moduleRegistry['pageModels'],
  'blueprints' => $moduleRegistry['blueprints'],
  'sections' => [
    'modules' => include __DIR__ . '/lib/sections/modules.php'
  ],
  'hooks' => [
    'panel.route:before' => function ($route, $path, $method) {
      if ($path && preg_match('#^pages/(.+)\+modules$#', $path, $matches)) {
        $parentId = str_replace('+', '/', $matches[1]);
        if ($parent = kirby()->page($parentId)) {
          \Kirby\Panel\Panel::go($parent->panel()->url());
        }
      }
      return $route;
    },
    'page.move:before' => function ($page, $parent) {
      if (!$page->isModule()) return;

      $grandparent = $parent->parent();
      $section = $grandparent?->blueprint()->section('modules');
      $allowed = $section?->templates() ?? [];

      if ($allowed && !in_array($page->intendedTemplate()->name(), $allowed)) {
        throw new \Kirby\Exception\PermissionException(t('modules.move.notallowed'));
      }
    }
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
    },
    'uniqueModuleTitle' => function () {
      $title = $this->blueprint()->title();
      $siblings = $this->parent()?->childrenAndDrafts() ?? new Pages();
      $count = $siblings->filterBy('intendedTemplate', $this->intendedTemplate()->name())->count();
      return $count > 0 ? $title . ' ' . ($count + 1) : $title;
    }
  ],
  'areas' => [
    'modules' => function () {
      return [
        'dialogs' => [
          'modules/create' => [
            'pattern' => 'modules/create',
            'load' => fn() => ModuleCreateDialog::load(),
            'submit' => fn() => ModuleCreateDialog::submit(),
          ],
          'modules/change-type' => [
            'pattern' => 'modules/change-type',
            'load' => function () {
              $pageId = kirby()->request()->get('page');
              $page = kirby()->page(str_replace('+', '/', $pageId));

              $options = [];
              foreach ($page->blueprints() as $bp) {
                $options[] = ['text' => $bp['title'], 'value' => $bp['name']];
              }

              return [
                'component' => 'k-form-dialog',
                'props' => [
                  'fields' => [
                    'template' => [
                      'type' => 'select',
                      'label' => t('modules.create.type'),
                      'options' => $options,
                      'required' => true,
                      'empty' => false,
                    ]
                  ],
                  'value' => [
                    'page' => $pageId,
                    'template' => $page->intendedTemplate()->name()
                  ],
                  'submitButton' => t('change'),
                ]
              ];
            },
            'submit' => function () {
              $input = kirby()->request()->body()->toArray();
              $pageId = kirby()->request()->get('page');
              $page = kirby()->page(str_replace('+', '/', $pageId));
              $page->changeTemplate($input['template']);
              return true;
            }
          ]
        ]
      ];
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
