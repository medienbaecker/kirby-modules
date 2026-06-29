<?php

namespace Medienbaecker\Modules;

use Kirby\Data\Data;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;

class ModuleRegistry
{
  private static ?array $cache = null;
  private static ?array $previews = null;

  public static function load(): array
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
    $blueprintNames = array_map(
      fn($name) => str_replace('pages/', '', $name),
      array_keys($registry['blueprints'])
    );

    foreach ($registry['blueprints'] as &$blueprint) {
      $options = $blueprint['options'] ?? [];
      if (is_array($options) && !isset($options['changeTemplate'])) {
        $blueprint['options']['changeTemplate'] = $blueprintNames;
      }
    }
    unset($blueprint);

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
    return isset(self::load()['blueprints']['pages/' . $template]);
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
      'icon'    => self::load()['blueprints']['pages/' . $template]['icon'] ?? 'box',
    ];
  }

  // 'text' and 'module.text' are interchangeable in all plugin options;
  // this returns the full template name.
  public static function qualify(string $name): string
  {
    return Str::startsWith($name, 'module.') ? $name : 'module.' . $name;
  }

  // Like qualify(), but for creating modules: throws when the type is
  // missing or has no blueprint.
  public static function template(?string $type): string
  {
    if (!$type) {
      throw new InvalidArgumentException('Module type is required');
    }

    $template = self::qualify($type);
    if (!self::hasBlueprint($template)) {
      throw new InvalidArgumentException('Unknown module type "' . $type . '"');
    }

    return $template;
  }

  public static function add(array $registry, string $name, string $blueprintPath, string $snippetPath): array
  {
    $name = self::qualify($name);

    if (isset($registry['blueprints']['pages/' . $name]) || !F::exists($blueprintPath)) {
      return $registry;
    }

    $defaults = [
      'options' => ['changeStatus' => false, 'changeTitle' => false],
      'navigation' => ['status' => 'all', 'template' => 'all'],
      'icon' => 'box',
      'buttons' => ['open', 'preview', '-', 'settings', 'languages', 'modules.visibility'],
    ];
    $blueprintArray = array_merge($defaults, Data::read($blueprintPath));

    // Force status/redirect (modules are always listed; visibility is the
    // `hidden` flag) while keeping any author-supplied create config.
    $create = $blueprintArray['create'] ?? null;
    $create = is_array($create) ? $create : [];
    $blueprintArray['create'] = array_merge($create, [
      'status'   => 'listed',
      'redirect' => false,
    ]);

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
    return self::uniqueSlug($parentId, Str::slug(str_replace('module.', '', $template)));
  }

  // Base on the source slug so #anchor duplicates as #anchor-2. The trailing
  // -N is stripped first so #anchor-2 yields #anchor-3, not #anchor-2-2.
  public static function duplicateSlug(string $parentId, string $sourceSlug): ?string
  {
    return self::uniqueSlug($parentId, preg_replace('/-\d+$/', '', $sourceSlug));
  }

  public static function uniqueSlug(string $parentId, string $slug): ?string
  {
    $parentId = str_replace('+', '/', $parentId);
    $parentId = preg_replace('#^pages/#', '', $parentId);

    if (!$parent = kirby()->page($parentId)) {
      return null;
    }

    // Match PageRules::create(), which checks the slug against drafts too.
    $siblings = $parent->childrenAndDrafts();
    while ($siblings->findBy('slug', $slug)) {
      $slug = Str::increment($slug, '-', 2);
    }
    return $slug;
  }
}
