<?php

use Kirby\Cms\Blueprint;
use Kirby\Cms\Site;
use Kirby\Toolkit\I18n;
use Medienbaecker\Modules\ModuleRegistry;
use Medienbaecker\Modules\ModuleSectionRoutes;
use Medienbaecker\Modules\ModuleSectionItem;

$allBlueprints = array_values(array_map(
  fn(string $name) => substr($name, strlen('pages/')),
  array_filter(
    array_keys(ModuleRegistry::load()['blueprints']),
    fn(string $name) => str_starts_with($name, 'pages/module.')
  )
));

return [
  'mixins' => ['headline', 'parent', 'sort', 'empty', 'min', 'max'],

  'props' => [
    'default' => fn(?string $default = null) => $default,
    'templatesIgnore' => fn(array $templatesIgnore = []) => $templatesIgnore,

    // The sort mixin reads $this->query, which core's pages section defines
    // as a prop (no mixin does) — declare it so the dependency is explicit.
    'query' => fn() => null,

    // Both short ('text') and full ('module.text') names are accepted here,
    // in templatesIgnore and in default.
    'templates' => function ($templates = null) use ($allBlueprints) {
      $blueprints = $templates
        ? array_map(fn($name) => ModuleRegistry::qualify($name), $templates)
        : $allBlueprints;

      if ($this->templatesIgnore) {
        $ignore = array_map(fn($name) => ModuleRegistry::qualify($name), $this->templatesIgnore);
        $blueprints = array_values(array_diff($blueprints, $ignore));
      }

      if ($this->default) {
        $name = ModuleRegistry::qualify($this->default);
        if (in_array($name, $blueprints, true)) {
          $blueprints = array_values(array_unique(array_merge([$name], $blueprints)));
        }
      }

      return $blueprints;
    },

    'empty' => fn($empty = null) => $empty ?? I18n::translate('modules.empty'),
    'label' => fn($label = null) => $label ?? I18n::translate('modules.plural'),

    'parent' => function ($parent = null) {
      $modelType = $this->model() instanceof Site ? 'site' : 'page';
      return $this->model()->find($this->name)
        ? "{$modelType}.find(\"{$this->name}\")"
        : $parent;
    },
  ],

  'methods' => [
    'blueprints' => function () {
      $blueprints = [];
      foreach ($this->templates as $template) {
        try {
          $props = Blueprint::load('pages/' . $template);
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
    // Computed props evaluate in definition order; `modules` must come
    // first because `total` (and through it `add` and `errors`) reads it.
    'modules' => function () {
      $modulesPage = $this->model()->find($this->name);
      if (!$modulesPage) return [];

      $modules = [];
      foreach ($modulesPage->children() as $child) {
        $modules[] = ModuleSectionItem::for($child);
      }
      return $modules;
    },

    'total' => fn() => count($this->modules),
    'add'   => fn() => !$this->isFull(),

    // Verbatim copy of core's pages section errors computed (sections can't
    // inherit from each other) — keep in sync with
    // kirby/config/sections/pages.php on Kirby updates.
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

      if (empty($errors)) {
        return [];
      }

      return [
        $this->name => [
          'label'   => $this->headline,
          'message' => $errors,
        ]
      ];
    },
  ],

  'api' => function () {
    return ModuleSectionRoutes::routes();
  },

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
  },
];
