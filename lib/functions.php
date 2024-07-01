<?php

use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;
use Kirby\Data\Yaml;

function createModuleRegistry() {

  $registry = ['blueprints' => [], 'templates' => [], 'pageModels' => []];

  $blueprintsFolder = kirby()->root('blueprints');
  $snippetsFolder = kirby()->root('snippets');
  $modulesFolder = kirby()->root('site') . "/modules";

  // ----------------------------------------------------------------------
  // Register modules in site/modules
  // ----------------------------------------------------------------------

  foreach (Dir::dirs($modulesFolder) as $folder) {
    $blueprintPath = $modulesFolder . "/". $folder . "/" . $folder . ".yml";
    $templatePath = $modulesFolder . "/". $folder . "/" . $folder . ".php";
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
  // Legacy registry (deprecated)
  // ----------------------------------------------------------------------

  $moduleBlueprints = array_filter(kirby()->blueprints(), function($blueprint) {
    return Str::startsWith($blueprint, 'module.');
  });

  // Check if there are any module blueprints and throw a deprecation warning
  if(count($moduleBlueprints)) {
    deprecated('This folder structure is deprecated. Please move your module blueprints to site/blueprints/modules/ and your module templates to site/snippets/modules/.');
    foreach($moduleBlueprints as $moduleBlueprint) {
      $blueprintPath = $blueprintsFolder . "/pages/" . $moduleBlueprint . ".yml";
      $templatePath = $snippetsFolder . "/modules/" . $moduleBlueprint . ".php";
      $registry = addToModulesRegistry($registry, $moduleBlueprint, $blueprintPath, $templatePath);
    }
  }

  // ----------------------------------------------------------------------
  // Populate changeTemplate option
  // ----------------------------------------------------------------------

  $blueprintNames = array_keys($registry['blueprints']);
  $blueprintNames = array_map(function($blueprintName) {
    return Str::replace($blueprintName, 'pages/', '');
  }, $blueprintNames);

  foreach($registry['blueprints'] as &$blueprint) {
    if(
      !isset($blueprint['options']) ||
      (
        is_array($blueprint['options']) && !isset($blueprint['options']['changeTemplate']
      ))
    ) {
      $blueprint['options']['changeTemplate'] = $blueprintNames;
    }
  }

  // ----------------------------------------------------------------------
  // Add modules container blueprint with pages section and redirect field
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
      ],
      'fields' => [
        'fields' => [
          'modules_redirect' => true
        ]
      ]
    ],
  ];
  
  // ----------------------------------------------------------------------
  // Add modules container model
  // ----------------------------------------------------------------------

  $registry['pageModels']['modules'] = 'ModulesPage';

  return $registry;

}

function addToModulesRegistry(array $registry, string $name, string $blueprintPath, string $templatePath) {

  // Check if blueprint already exists in registry
  if(array_key_exists('pages/module.'. $name, $registry['blueprints'])) {
    return;
  }

  // Check if blueprint exists
  if(!F::exists($blueprintPath)) {
    return;
  }

  // Turn the blueprint into an array
  $blueprintArray = Yaml::read($blueprintPath);

  // Set up default values for status and navigation
  if(!array_key_exists('status', $blueprintArray)) {
    $blueprintArray['status'] = [
      'draft' => [
        'text' => false
      ],
      'listed' => [
        'text' => false
      ]
    ];
  }
  if(!array_key_exists('navigation', $blueprintArray)) {
    $blueprintArray['navigation'] = ['status' => 'all', 'template' => 'all'];
  }

  // Adjust the create blueprint option if it doesn't exist
  if(!array_key_exists('create', $blueprintArray)) {

    $blueprintArray['create'] = [];

    // Add slug field if autoslug is enabled
    if(option('medienbaecker.modules.autoslug') === true) {
      $blueprintArray['create']['slug'] = '{{ page.uniqueModuleSlug }}';
    }

    // Set status to listed if autopublish is enabled
    if(option('medienbaecker.modules.autopublish') === true) {
      $blueprintArray['create']['status'] = 'listed';
    }

    // Disable redirect if the redirect option is not explicitely set to true
    if(option('medienbaecker.modules.redirect') !== true) {
      $blueprintArray['create']['redirect'] = false;
    }

  }

  // Add module prefix to blueprint name
  if(Str::startsWith($name, 'module.') === false) {
    $name = 'module.' . $name;
  }

  // Add to registry
  $registry['blueprints']['pages/' . $name] = $blueprintArray;
  $registry['templates'][$name] = $templatePath;
  $registry['pageModels'][$name] = option('medienbaecker.modules.model', 'ModulePage');

  return $registry;

}