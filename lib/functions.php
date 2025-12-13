<?php

use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;
use Kirby\Data\Yaml;

function createModuleRegistry()
{

  $registry = ['blueprints' => [], 'templates' => [], 'snippets' => [], 'pageModels' => []];

  $blueprintsFolder = kirby()->root('blueprints');
  $snippetsFolder = kirby()->root('snippets');
  $modulesFolder = kirby()->root('site') . "/modules";

  // ----------------------------------------------------------------------
  // Register modules in site/modules
  // ----------------------------------------------------------------------

  foreach (Dir::dirs($modulesFolder) as $folder) {
    $blueprintPath = $modulesFolder . "/" . $folder . "/" . $folder . ".yml";
    $templatePath = $modulesFolder . "/" . $folder . "/" . $folder . ".php";
    $registry = addToModulesRegistry($registry, $folder, $blueprintPath, $templatePath);
  }

  // ----------------------------------------------------------------------
  // Register modules in site/blueprints/modules and site/snippets/modules
  // ----------------------------------------------------------------------

  $moduleBlueprintsFolder = kirby()->root('blueprints') . "/modules";
  $moduleTemplatesFolder = kirby()->root('snippets') . "/modules";

  foreach (Dir::files($moduleBlueprintsFolder) as $file) {
    $filename = F::name($file);
    $blueprintPath = $moduleBlueprintsFolder . "/" . $filename . ".yml";
    $templatePath = $moduleTemplatesFolder . "/" . $filename . ".php";
    $registry = addToModulesRegistry($registry, $filename, $blueprintPath, $templatePath);
  }

  // ----------------------------------------------------------------------
  // Populate changeTemplate option
  // ----------------------------------------------------------------------

  $blueprintNames = array_keys($registry['blueprints']);
  $blueprintNames = array_map(function ($blueprintName) {
    return Str::replace($blueprintName, 'pages/', '');
  }, $blueprintNames);

  foreach ($registry['blueprints'] as &$blueprint) {
    if (
      !isset($blueprint['options']) ||
      (
        is_array($blueprint['options']) && !isset(
          $blueprint['options']['changeTemplate']
        ))
    ) {
      $blueprint['options']['changeTemplate'] = $blueprintNames;
    }
  }

  // ----------------------------------------------------------------------
  // Add modules container blueprint with pages section
  // ----------------------------------------------------------------------

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

  // ----------------------------------------------------------------------
  // Add modules container model
  // ----------------------------------------------------------------------

  $registry['pageModels']['modules'] = 'ModulesPage';

  return $registry;
}

function addToModulesRegistry(array $registry, string $name, string $blueprintPath, string $snippetPath)
{
  // Check if blueprint already exists in registry
  if (array_key_exists('pages/module.' . $name, $registry['blueprints'])) {
    return $registry;
  }

  // Check if blueprint exists
  if (!F::exists($blueprintPath)) {
    return $registry;
  }

  // Turn the blueprint into an array and merge with defaults
  $defaults = [
    'status' => ['draft' => ['text' => false], 'listed' => ['text' => false]],
    'navigation' => ['status' => 'all', 'template' => 'all'],
    'icon' => 'box'
  ];
  $blueprintArray = array_merge($defaults, Yaml::read($blueprintPath));

  // Adjust the create blueprint option if it doesn't exist
  if (!array_key_exists('create', $blueprintArray)) {
    $blueprintArray['create'] = [];

    if (option('medienbaecker.modules.autoslug') === true) {
      $blueprintArray['create']['slug'] = '{{ page.uniqueModuleSlug }}';
    }

    if (option('medienbaecker.modules.autopublish') === true) {
      $blueprintArray['create']['status'] = 'listed';
    }

    if (option('medienbaecker.modules.redirect') !== true) {
      $blueprintArray['create']['redirect'] = false;
    }
  }

  // Add module prefix to blueprint name
  if (Str::startsWith($name, 'module.') === false) {
    $name = 'module.' . $name;
  }

  // Extract short name for snippet registration
  $shortName = str_replace('module.', '', $name);

  // Add to registry (both templates and snippets for compatibility)
  $registry['blueprints']['pages/' . $name] = $blueprintArray;
  $registry['templates'][$name] = $snippetPath;
  $registry['snippets']['modules/' . $shortName] = $snippetPath;
  $registry['pageModels'][$name] = option('medienbaecker.modules.model', 'ModulePage');

  return $registry;
}
