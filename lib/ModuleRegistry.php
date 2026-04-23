<?php

namespace Medienbaecker\Modules;

use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;
use Kirby\Data\Yaml;

/**
 * Registers module blueprints, templates, snippets, and models
 */
class ModuleRegistry
{
  private static ?array $cache = null;

  /**
   * Build the full module registry
   *
   * @return array{blueprints: array, templates: array, snippets: array, pageModels: array}
   */
  public static function create(): array
  {
    if (self::$cache !== null) {
      return self::$cache;
    }

    $registry = ['blueprints' => [], 'templates' => [], 'snippets' => [], 'pageModels' => []];

    $modulesFolder = kirby()->root('site') . '/modules';

    // Register modules in site/modules
    foreach (Dir::dirs($modulesFolder) as $folder) {
      $blueprintPath = $modulesFolder . '/' . $folder . '/' . $folder . '.yml';
      $templatePath = $modulesFolder . '/' . $folder . '/' . $folder . '.php';
      $registry = static::add($registry, $folder, $blueprintPath, $templatePath);
    }

    // Register modules in site/blueprints/modules and site/snippets/modules
    $moduleBlueprintsFolder = kirby()->root('blueprints') . '/modules';
    $moduleTemplatesFolder = kirby()->root('snippets') . '/modules';
    foreach (Dir::files($moduleBlueprintsFolder) as $file) {
      $filename = F::name($file);
      $blueprintPath = $moduleBlueprintsFolder . '/' . $filename . '.yml';
      $templatePath = $moduleTemplatesFolder . '/' . $filename . '.php';
      $registry = static::add($registry, $filename, $blueprintPath, $templatePath);
    }

    // Populate changeTemplate option with all module blueprints
    // to allow changing templates in the panel
    $blueprintNames = array_keys($registry['blueprints']);
    $blueprintNames = array_map(fn($name) => Str::replace($name, 'pages/', ''), $blueprintNames);

    foreach ($registry['blueprints'] as &$blueprint) {
      if (
        !isset($blueprint['options']) ||
        (is_array($blueprint['options']) && !isset($blueprint['options']['changeTemplate']))
      ) {
        $blueprint['options']['changeTemplate'] = $blueprintNames;
      }
    }

    // Add modules container blueprint
    $registry['blueprints']['pages/modules'] = [
      'title' => 'Modules',
      'image' => [
        'query' => false,
      ],
      'icon' => 'modules',
      'options' => [
        'changeSlug' => false,
        'changeStatus' => false,
        'changeTemplate' => false,
      ],
      'sections' => [
        'pages' => [
          'templates' => $blueprintNames
        ]
      ],
    ];

    // Add modules container model
    $registry['pageModels']['modules'] = ModulesPage::class;

    self::$cache = $registry;
    return $registry;
  }

  /**
   * Whether a blueprint is registered for the given intended template name
   * (e.g. 'module.text')
   */
  public static function hasBlueprint(string $template): bool
  {
    return isset(self::create()['blueprints']['pages/' . $template]);
  }

  /**
   * Register a single module into the registry
   */
  public static function add(array $registry, string $name, string $blueprintPath, string $snippetPath): array
  {
    // Prevent duplicates
    if (array_key_exists('pages/module.' . $name, $registry['blueprints'])) {
      return $registry;
    }

    // If no blueprint exists, we can't register the module
    if (!F::exists($blueprintPath)) {
      return $registry;
    }

    // Combine the blueprint yaml with some defaults
    $defaults = [
      'status' => ['draft' => ['text' => false], 'listed' => ['text' => false]],
      'navigation' => ['status' => 'all', 'template' => 'all'],
      'icon' => 'box'
    ];
    $blueprintArray = array_merge($defaults, Yaml::read($blueprintPath));

    if (!array_key_exists('create', $blueprintArray)) {
      $blueprintArray['create'] = [];
      $blueprintArray['create']['title'] = '{{ page.uniqueModuleTitle }}';

      if ($status = option('medienbaecker.modules.create.status')) {
        $blueprintArray['create']['status'] = $status;
      }

      if (option('medienbaecker.modules.create.redirect') !== true) {
        $blueprintArray['create']['redirect'] = false;
      }
    }

    if (Str::startsWith($name, 'module.') === false) {
      $name = 'module.' . $name;
    }

    $shortName = str_replace('module.', '', $name);

    $registry['blueprints']['pages/' . $name] = $blueprintArray;
    $registry['templates'][$name] = $snippetPath;
    $registry['snippets']['modules/' . $shortName] = $snippetPath;
    $model = option('medienbaecker.modules.model', ModulePage::class);
    if ($model === 'ModulePage') $model = ModulePage::class;
    $registry['pageModels'][$name] = $model;

    return $registry;
  }

  /**
   * Generate a unique slug for a new module based on the template name
   * e.g. 'text', 'text-2', 'text-3'
   */
  public static function generateSlug(string $parentId, string $template): ?string
  {
    $parentId = str_replace('+', '/', $parentId);
    $parentId = preg_replace('#^pages/#', '', $parentId);

    if (!$parent = kirby()->page($parentId)) {
      return null;
    }

    $shortName = str_replace('module.', '', $template);
    $slug = Str::slug($shortName);
    $siblings = $parent->childrenAndDrafts();

    if ($siblings->findBy('slug', $slug)) {
      $i = 2;
      while ($siblings->findBy('slug', $slug . '-' . $i)) $i++;
      $slug = $slug . '-' . $i;
    }

    return $slug;
  }
}
