<?php

use Kirby\Form\Form;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\I18n;

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
foreach ($excludedModules as $excludedModule) {
  $key = array_search('module.' . $excludedModule, $blueprints);
  if ($key !== false) {
    unset($blueprints[$key]);
  }
}

return [
  'mixins' => ['headline', 'parent', 'sort', 'empty', 'min', 'max'],
  'props' => [
    'templates' => function ($templates = null) use ($blueprints) {
      return $templates ?? $blueprints;
    },
    'empty' => function ($empty = null) {
      return $empty ?? I18n::translate('modules.empty');
    },
    'headline' => function ($headline = null) {
      return $headline ?? I18n::translate('modules');
    },
    'parent' => function ($parent = null) {
      $class = get_class($this->model()) === 'Kirby\Cms\Site' ? 'site' : 'page';
      return $this->model()->find('modules') ? $class . '.find("modules")' : $parent;
    },
  ],
  'methods' => [
    'blueprints' => function () {
      $blueprints = [];
      foreach ($this->templates as $template) {
        try {
          $props = \Kirby\Cms\Blueprint::load('pages/' . $template);
          $blueprints[] = [
            'name'  => basename($props['name']),
            'title' => $props['title'],
          ];
        } catch (\Throwable) {
          $blueprints[] = [
            'name'  => $template,
            'title' => ucfirst($template),
          ];
        }
      }
      return $blueprints;
    },
  ],
  'computed' => [
    'modules' => function () {
      $modulesPage = $this->model()->find('modules');
      if (!$modulesPage) return [];

      $modules = [];
      foreach ($modulesPage->childrenAndDrafts() as $child) {
        if ($child->isUnlisted()) continue;

        $modules[] = [
          'id'                => $child->id(),
          'title'             => $child->title()->value(),
          'template'          => $child->intendedTemplate()->name(),
          'moduleName'        => $child->moduleName(),
          'icon'              => $child->blueprint()->icon() ?? 'box',
          'status'            => $child->status(),
          'hasFields'         => count($child->blueprint()->fields()) > 0,
          'hasPendingChanges' => $child->version('changes')->exists(),
          'tabs'              => $child->blueprint()->tabs(),
          'link'              => $child->panel()->url(),
          'permissions'       => [
            'update'     => $child->permissions()->can('update'),
            'delete'     => $child->permissions()->can('delete'),
            'changeSort' => $child->permissions()->can('sort'),
          ],
          'lock' => $child->lock()?->toArray(),
        ];
      }

      return $modules;
    },
  ],
  'api' => function () {
    $resolveModule = function (string $childId) {
      $child = kirby()->page(str_replace('+', '/', $childId));
      if (!$child) {
        throw new \Kirby\Exception\NotFoundException('Module not found');
      }
      return $child;
    };

    return [
      [
        'pattern' => 'fields/(:any)',
        'method'  => 'GET',
        'action'  => function (string $childId) use ($resolveModule) {
          $child = $resolveModule($childId);

          if ($child->version('changes')->exists()) {
            $values = $child->version('changes')->content()->toArray();
            $form = Form::for($child, ['values' => $values]);
          } else {
            $form = Form::for($child);
          }

          return [
            'fields' => $form->fields()->toProps(),
            'values' => $form->fields()->toFormValues(),
          ];
        }
      ],
      [
        'pattern' => 'duplicate/(:any)',
        'method'  => 'POST',
        'action'  => function (string $childId) use ($resolveModule) {
          $child = $resolveModule($childId);
          $duplicate = $child->duplicate();
          $duplicate->changeStatus('listed', $child->num() + 1);
          return ['status' => 'ok'];
        }
      ],
      [
        'pattern' => 'sort',
        'method'  => 'POST',
        'action'  => function () {
          $ids = $this->requestBody('ids');
          $position = 1;
          foreach ($ids as $id) {
            if ($page = kirby()->page($id)) {
              if ($page->isDraft()) continue;
              $page->changeStatus('listed', $position);
              $position++;
            }
          }
          return ['status' => 'ok'];
        }
      ],
      [
        'pattern' => 'toggle-visibility/(:any)',
        'method'  => 'POST',
        'action'  => function (string $childId) use ($resolveModule) {
          $child = $resolveModule($childId);
          kirby()->impersonate('kirby');

          if ($child->isDraft()) {
            $child->changeStatus('listed');
          } else {
            $child->changeStatus('draft');
          }

          return ['status' => 'ok'];
        }
      ]
    ];
  },
  'toArray' => function () {
    $modulesPage = $this->model()->find('modules');
    return [
      'headline'  => $this->headline,
      'modules'   => $this->modules,
      'empty'     => $this->empty,
      'sortable'  => $this->sortable,
      'link'      => $modulesPage ? 'pages/' . str_replace('/', '+', $modulesPage->id()) : null,
      'templates' => $this->templates,
    ];
  }
];
