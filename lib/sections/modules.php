<?php

use Kirby\Filesystem\Dir;
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

  // ---------------------------------------------------------------
  // Blueprint-configurable section settings
  // ---------------------------------------------------------------

  'props' => [

    'default' => function (string $default = null) {
      return $default;
    },

    'templatesIgnore' => function (array $templatesIgnore = []) {
      return $templatesIgnore;
    },

    'templates' => function ($templates = null) use ($allBlueprints) {
      $blueprints = $templates ?? $allBlueprints;

      if ($this->templatesIgnore) {
        $blueprints = array_values(array_filter($blueprints, function ($bp) {
          $short = str_replace('module.', '', $bp);
          return !in_array($short, $this->templatesIgnore) && !in_array($bp, $this->templatesIgnore);
        }));
      }

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

    'label' => function ($label = null) {
      return $label ?? I18n::translate('modules.plural');
    },

    'parent' => function ($parent = null) {
      $class = get_class($this->model()) === 'Kirby\Cms\Site' ? 'site' : 'page';
      return $this->model()->find($this->name) ? $class . '.find("' . $this->name . '")' : $parent;
    },
  ],

  // ---------------------------------------------------------------
  // Methods
  // ---------------------------------------------------------------

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

  // ---------------------------------------------------------------
  // Computed methods
  // ---------------------------------------------------------------

  'computed' => [
    // For min/max validation
    'total' => function () {
      return count($this->modules ?? []);
    },
    'add' => function () {
      return !$this->isFull();
    },
    'errors' => function () {
      $errors = [];

      if ($this->validateMax() === false) {
        $errors['max'] = I18n::template('error.section.pages.max.' . I18n::form($this->max), [
          'max'     => $this->max,
          'section' => $this->headline
        ]);
      }

      if ($this->validateMin() === false) {
        $errors['min'] = I18n::template('error.section.pages.min.' . I18n::form($this->min), [
          'min'     => $this->min,
          'section' => $this->headline
        ]);
      }

      if (empty($errors) === true) {
        return [];
      }

      return [
        $this->name => [
          'label'   => $this->headline,
          'message' => $errors,
        ]
      ];
    },

    // Loads all modules from the container page
    'modules' => function () {
      $modulesPage = $this->model()->find($this->name);
      if (!$modulesPage) return [];

      $modules = [];

      // Listed pages sort by num(), drafts by fractional moduleSort (e.g. 3.001)
      $defaultLanguage = kirby()->defaultLanguage()?->code();
      $children = $modulesPage->childrenAndDrafts()->filter(
        fn($child) => !$child->isUnlisted()
      )->sortBy(function ($child) use ($defaultLanguage) {
        if ($child->isDraft()) {
          $sort = (float) $child->content($defaultLanguage)->moduleSort()->value();
          return $sort ?: PHP_FLOAT_MAX;
        }
        return (float) ($child->num() ?? PHP_INT_MAX);
      }, 'asc');

      // When a blueprint is deleted, its pageModel isn't registered either,
      // so the page class is the base Page — any ModulePage helper call
      // falls through Kirby's __call magic to a content field. Ask the
      // registry directly instead.
      foreach ($children as $child) {
        $templateName = $child->intendedTemplate()->name();
        $hasTemplate = ModuleRegistry::hasBlueprint($templateName);
        $blueprint = $hasTemplate ? $child->blueprint() : null;

        $modules[] = [
          'id'                => $child->id(),
          'slug'              => $child->slug(),
          'title'             => $child->title()->value(),
          'template'          => $templateName,
          'hasTemplate'       => $hasTemplate,
          'moduleName'        => $blueprint ? (string) $blueprint->title() : I18n::translate('modules.missingTemplate'),
          'icon'              => $blueprint ? ($blueprint->icon() ?? 'box') : 'alert',
          'status'            => $child->status(),
          'hasFields'         => $blueprint && count($blueprint->fields()) > 0,
          'hasPendingChanges' => $child->version('changes')->exists('*'),
          'tabs'              => $blueprint ? $blueprint->tabs() : [],
          'link'              => $child->panel()->url(),
          'permissions'       => [
            'update'     => $child->permissions()->can('update'),
            'delete'     => $child->permissions()->can('delete'),
            'changeSort' => $child->permissions()->can('sort'),
          ],
          'lock' => $child->lock()?->toArray(),

          // Signed preview URL for drafts (token + _module param)
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

  // ---------------------------------------------------------------
  // Section endpoints called by the Vue frontend
  // ---------------------------------------------------------------

  'api' => function () {
    $resolveModule = function (string $childId) {
      $child = kirby()->page(str_replace('+', '/', $childId));
      if (!$child) {
        throw new \Kirby\Exception\NotFoundException('Module not found');
      }
      return $child;
    };

    return [
      // Load form fields and values for inline editing
      [
        'pattern' => 'fields/(:any)',
        'method'  => 'GET',
        'action'  => function (string $childId) use ($resolveModule) {
          $child = $resolveModule($childId);

          // Use pending changes if they exist, otherwise published content
          $language = kirby()->language()?->code() ?? 'default';
          if ($child->version('changes')->exists($language)) {
            $values = $child->version('changes')->content($language)->toArray();
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

      // Duplicate a module — always stays a draft, sorted right after source
      [
        'pattern' => 'duplicate/(:any)',
        'method'  => 'POST',
        'action'  => function (string $childId) use ($resolveModule) {
          $child = $resolveModule($childId);
          $duplicate = $child->duplicate(null, ['files' => true]);

          // Preserve in-progress inline edits: Kirby's duplicate() skips
          // subdirectories by default, so the _changes/ version dir isn't
          // copied. _changes isn't semantically a "child" page, so using
          // children: true to drag it along would be a misnomer.
          $changesDir = $child->root() . '/_changes';
          if (is_dir($changesDir)) {
            Dir::copy($changesDir, $duplicate->root() . '/_changes');
          }

          kirby()->impersonate('kirby', function () use ($duplicate, $child) {
            $defaultLanguage = kirby()->defaultLanguage()?->code();
            $sort = $child->isDraft()
              ? (float) $child->content($defaultLanguage)->moduleSort()->value()
              : (float) $child->num();
            $duplicate->update(['moduleSort' => $sort + 0.0001], $defaultLanguage);
          });
          return ['status' => 'ok'];
        }
      ],

      // Persist sort order — interleaves drafts with listed pages
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
                // Drafts get fractional sort values (e.g. 3.001, 3.002)
                $draftCounter++;
                kirby()->impersonate('kirby', function () use ($page, $lastListedNum, $draftCounter) {
                  $defaultLanguage = kirby()->defaultLanguage()?->code();
                  $page->update(['moduleSort' => $lastListedNum + $draftCounter * 0.001], $defaultLanguage);
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

      // Delete all modules in this section's container
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

      // Toggle between draft and listed status
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

      // Auto-create the container page on demand (section name = slug)
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

  // ---------------------------------------------------------------
  // Response structure for the Vue frontend
  // ---------------------------------------------------------------

  'toArray' => function () {
    $modulesPage = $this->model()->find($this->name);
    return [
      'data'    => $this->modules,
      'errors'  => $this->errors,
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
