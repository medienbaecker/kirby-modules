<?php

use Kirby\Form\Form;
use Kirby\Http\Uri;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\I18n;
use Medienbaecker\Modules\ModuleRegistry;

$registry = ModuleRegistry::create();

$allBlueprints = [];
foreach ($registry['blueprints'] as $blueprint => $file) {
  if (Str::startsWith($blueprint, 'pages/module.')) {
    $allBlueprints[] = str_replace('pages/', '', $blueprint);
  }
}

return [
  'mixins' => ['headline', 'parent', 'sort', 'empty', 'min', 'max'],
  'props' => [
    'default' => function (string $default = null) {
      return $default;
    },
    'templatesIgnore' => function (array $templatesIgnore = []) {
      return $templatesIgnore;
    },
    'templates' => function ($templates = null) use ($allBlueprints) {
      $blueprints = $templates ?? $allBlueprints;

      // Filter ignored templates
      if ($this->templatesIgnore) {
        $blueprints = array_values(array_filter($blueprints, function ($bp) {
          $short = str_replace('module.', '', $bp);
          return !in_array($short, $this->templatesIgnore) && !in_array($bp, $this->templatesIgnore);
        }));
      }

      // Move default to top
      if ($this->default) {
        $name = 'module.' . $this->default;
        $key = array_search($name, $blueprints);
        if ($key !== false) {
          $item = $blueprints[$key];
          unset($blueprints[$key]);
          array_unshift($blueprints, $item);
          $blueprints = array_values($blueprints);
        }
      }

      return $blueprints;
    },
    'empty' => function ($empty = null) {
      return $empty ?? I18n::translate('modules.empty');
    },
    'headline' => function ($headline = null) {
      return $headline ?? I18n::translate('modules');
    },
    'parent' => function ($parent = null) {
      $class = get_class($this->model()) === 'Kirby\Cms\Site' ? 'site' : 'page';
      return $this->model()->find($this->name) ? $class . '.find("' . $this->name . '")' : $parent;
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
    'total' => function () {
      return count($this->modules ?? []);
    },
    'add' => function () {
      return !$this->isFull();
    },
    'modules' => function () {
      $modulesPage = $this->model()->find($this->name);
      if (!$modulesPage) return [];

      $modules = [];
      $children = $modulesPage->childrenAndDrafts()->filter(
        fn($child) => !$child->isUnlisted()
      )->sortBy(function ($child) {
        if ($child->isDraft()) {
          $sort = (float) $child->content()->moduleSort()->value();
          return $sort ?: PHP_FLOAT_MAX;
        }
        return (float) ($child->num() ?? PHP_INT_MAX);
      }, 'asc');

      foreach ($children as $child) {

        $modules[] = [
          'id'                => $child->id(),
          'slug'              => $child->slug(),
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
          'previewUrl' => $child->isDraft()
            ? (new Uri($child->page()->url(), [
                'query'    => ['_token' => $child->version('latest')->previewToken(), '_module' => $child->slug()],
                'fragment' => $child->slug()
              ]))->toString()
            : null,
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
          if ($child->isDraft()) {
            kirby()->impersonate('kirby', function () use ($duplicate, $child) {
              $sort = (float) $child->content()->moduleSort()->value();
              $duplicate->update(['moduleSort' => $sort + 0.0001]);
            });
          } else {
            $duplicate->changeStatus('listed', $child->num() + 1);
          }
          return ['status' => 'ok'];
        }
      ],
      [
        'pattern' => 'sort',
        'method'  => 'POST',
        'action'  => function () {
          $ids = $this->requestBody('ids');
          $lastListedNum = 0;
          $draftCounter = 0;
          foreach ($ids as $id) {
            if ($page = kirby()->page($id)) {
              if ($page->isDraft()) {
                $draftCounter++;
                kirby()->impersonate('kirby', function () use ($page, $lastListedNum, $draftCounter) {
                  $page->update(['moduleSort' => $lastListedNum + $draftCounter * 0.001]);
                });
              } else {
                $lastListedNum++;
                $draftCounter = 0;
                $page->changeStatus('listed', $lastListedNum);
              }
            }
          }
          return ['status' => 'ok'];
        }
      ],
      [
        'pattern' => 'deleteAll',
        'method'  => 'POST',
        'action'  => function () {
          $modulesPage = $this->section()->model()->find($this->section()->name());
          if ($modulesPage) {
            foreach ($modulesPage->childrenAndDrafts() as $child) {
              $child->delete(true);
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
      ],
      [
        'pattern' => 'create-container',
        'method'  => 'POST',
        'action'  => function () {
          $section = $this->section();
          $model = $section->model();
          $slug = $section->name();
          if (!$model->find($slug)) {
            kirby()->impersonate('kirby');
            $model->createChild([
              'content'  => ['title' => $section->headline()],
              'slug'     => $slug,
              'template' => 'modules',
            ])->publish();
          }
          return ['status' => 'ok'];
        }
      ]
    ];
  },
  'toArray' => function () {
    $modulesPage = $this->model()->find($this->name);
    return [
      'data'    => $this->modules,
      'options' => [
        'add'       => $this->add,
        'empty'     => $this->empty,
        'headline'  => $this->headline,
        'link'      => $modulesPage ? 'pages/' . str_replace('/', '+', $modulesPage->id()) : null,
        'max'       => $this->max,
        'min'       => $this->min,
        'sortable'  => $this->sortable,
        'templates' => $this->templates,
      ],
    ];
  }
];
