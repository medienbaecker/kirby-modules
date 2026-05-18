<?php

use Kirby\Cms\Blueprint;
use Kirby\Cms\Site;
use Kirby\Toolkit\I18n;
use Medienbaecker\Modules\ModuleRegistry;
use Medienbaecker\Modules\ModuleSectionApi;
use Medienbaecker\Modules\ModuleSectionItem;

$allBlueprints = array_values(array_map(
  fn(string $name) => substr($name, strlen('pages/')),
  array_filter(
    array_keys(ModuleRegistry::create()['blueprints']),
    fn(string $name) => str_starts_with($name, 'pages/module.')
  )
));

return [
  'mixins' => ['headline', 'parent', 'sort', 'empty', 'min', 'max'],

  'props' => [
    'default' => fn(?string $default = null) => $default,
    'templatesIgnore' => fn(array $templatesIgnore = []) => $templatesIgnore,

    'templates' => function ($templates = null) use ($allBlueprints) {
      $blueprints = $templates ?? $allBlueprints;

      if ($this->templatesIgnore) {
        $blueprints = array_values(array_filter($blueprints, function ($name) {
          $short = preg_replace('/^module\./', '', $name);
          return !in_array($short, $this->templatesIgnore, true)
            && !in_array($name, $this->templatesIgnore, true);
        }));
      }

      if ($this->default) {
        $name = 'module.' . $this->default;
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
    'total' => fn() => count($this->modules ?? []),
    'add'   => fn() => !$this->isFull(),

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

    'modules' => function () {
      $modulesPage = $this->model()->find($this->name);
      if (!$modulesPage) return [];

      $modules = [];
      foreach ($modulesPage->children() as $child) {
        $modules[] = ModuleSectionItem::for($child);
      }
      return $modules;
    },
  ],

  'api' => function () {
    return ModuleSectionApi::routes();
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
