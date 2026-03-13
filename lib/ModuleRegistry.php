<?php

namespace Medienbaecker\Modules;

use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;
use Kirby\Data\Yaml;

class ModuleRegistry
{
  public static function create(): array
  {
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

    // Populate changeTemplate option
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
      'options' => [
        'changeSlug' => false,
        'changeStatus' => false,
        'changeTemplate' => false
      ],
      'sections' => [
        'pages' => [
          'templates' => $blueprintNames
        ]
      ],
    ];

    // Add modules container model
    $registry['pageModels']['modules'] = 'ModulesPage';

    return $registry;
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
      'status' => ['draft' => ['text' => false], 'listed' => ['text' => false]],
      'navigation' => ['status' => 'all', 'template' => 'all'],
      'icon' => 'box'
    ];
    $blueprintArray = array_merge($defaults, Yaml::read($blueprintPath));

    if (!array_key_exists('create', $blueprintArray)) {
      $blueprintArray['create'] = [];
      $blueprintArray['create']['title'] = '{{ page.uniqueModuleTitle }}';

      if (option('medienbaecker.modules.autoslug') === true) {
        $blueprintArray['create']['slug'] = '{{ page.uniqueModuleSlug }}';
      }

      if (option('medienbaecker.modules.autopublish') !== false) {
        $blueprintArray['create']['status'] = 'listed';
      }

      if (option('medienbaecker.modules.redirect') !== true) {
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
    $registry['pageModels'][$name] = option('medienbaecker.modules.model', 'ModulePage');

    return $registry;
  }

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
