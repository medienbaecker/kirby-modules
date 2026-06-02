<?php

namespace Medienbaecker\Modules;

use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;
use Kirby\Data\Yaml;

class ModuleRegistry
{
  private static ?array $cache = null;
  private static ?array $previews = null;

  public static function create(): array
  {
    if (self::$cache !== null) {
      return self::$cache;
    }

    $registry = ['blueprints' => [], 'templates' => [], 'snippets' => [], 'pageModels' => []];

    $modulesFolder = kirby()->root('site') . '/modules';

    foreach (Dir::dirs($modulesFolder) as $folder) {
      $blueprintPath = $modulesFolder . '/' . $folder . '/' . $folder . '.yml';
      $templatePath = $modulesFolder . '/' . $folder . '/' . $folder . '.php';
      $registry = static::add($registry, $folder, $blueprintPath, $templatePath);
    }

    $moduleBlueprintsFolder = kirby()->root('blueprints') . '/modules';
    $moduleTemplatesFolder = kirby()->root('snippets') . '/modules';
    foreach (Dir::files($moduleBlueprintsFolder) as $file) {
      $filename = F::name($file);
      $blueprintPath = $moduleBlueprintsFolder . '/' . $filename . '.yml';
      $templatePath = $moduleTemplatesFolder . '/' . $filename . '.php';
      $registry = static::add($registry, $filename, $blueprintPath, $templatePath);
    }

    // Default changeTemplate to all module blueprints unless one is declared.
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

    $registry['pageModels']['modules'] = ModulesPage::class;

    self::$cache = $registry;
    return $registry;
  }

  public static function hasBlueprint(string $template): bool
  {
    return isset(self::create()['blueprints']['pages/' . $template]);
  }

  // Maps a module's short name to a preview image URL by scanning
  // assets/module-previews (e.g. text.png → module type 'text').
  public static function previewImages(): array
  {
    if (self::$previews !== null) {
      return self::$previews;
    }

    $kirby = kirby();
    $path = $kirby->root('assets') . '/module-previews';
    if (!is_dir($path)) {
      return self::$previews = [];
    }

    $relative = str_replace($kirby->root('index') . '/', '', $kirby->root('assets'));
    $previews = [];
    foreach (Dir::files($path) as $file) {
      $asset = asset($relative . '/module-previews/' . $file);
      $previews[$asset->name()] = $asset->url();
    }
    return self::$previews = $previews;
  }

  // Preview image URL + icon for a module template (e.g. 'module.text'),
  // used to decorate the type cards in the create/change-type dialogs.
  public static function typeVisuals(string $template): array
  {
    $shortName = str_replace('module.', '', $template);
    return [
      'preview' => self::previewImages()[$shortName] ?? null,
      'icon'    => self::create()['blueprints']['pages/' . $template]['icon'] ?? 'box',
    ];
  }

  public static function add(array $registry, string $name, string $blueprintPath, string $snippetPath): array
  {
    if (array_key_exists('pages/module.' . $name, $registry['blueprints'])) {
      return $registry;
    }

    if (!F::exists($blueprintPath)) {
      return $registry;
    }

    $defaults = [
      'options' => ['changeStatus' => false, 'changeTitle' => false],
      'navigation' => ['status' => 'all', 'template' => 'all'],
      'icon' => 'box',
      'buttons' => ['open', 'preview', '-', 'settings', 'languages', 'modules.visibility'],
    ];
    $blueprintArray = array_merge($defaults, Yaml::read($blueprintPath));

    if (!array_key_exists('create', $blueprintArray)) {
      $blueprintArray['create'] = [
        'title'    => '{{ page.blueprint.title }}',
        'status'   => 'listed',
        'redirect' => false,
      ];
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

  // e.g. 'text', 'text-2', 'text-3'
  public static function generateSlug(string $parentId, string $template): ?string
  {
    return self::buildSlug($parentId, Str::slug(str_replace('module.', '', $template)));
  }

  // Base on the source slug so #anchor duplicates as #anchor-2. The trailing
  // -N is stripped first so #anchor-2 yields #anchor-3, not #anchor-2-2.
  public static function duplicateSlug(string $parentId, string $sourceSlug): ?string
  {
    return self::buildSlug($parentId, preg_replace('/-\d+$/', '', $sourceSlug));
  }

  private static function buildSlug(string $parentId, string $slug): ?string
  {
    $parentId = str_replace('+', '/', $parentId);
    $parentId = preg_replace('#^pages/#', '', $parentId);

    if (!$parent = kirby()->page($parentId)) {
      return null;
    }

    $siblings = $parent->children();
    if ($siblings->findBy('slug', $slug)) {
      $i = 2;
      while ($siblings->findBy('slug', $slug . '-' . $i)) $i++;
      $slug = $slug . '-' . $i;
    }
    return $slug;
  }
}
