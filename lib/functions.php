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
  // Add modules container blueprint with redirect field
  // ----------------------------------------------------------------------
  
  $registry['blueprints']['pages/modules'] = [
    'title' => 'Modules',
    'options' => [
      'changeSlug' => false,
      'changeStatus' => false,
      'changeTemplate' => false
    ],
    'fields' => [
      'modules_redirect' => true
    ]
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
    $blueprintArray['status'] = ['draft' => true, 'listed' => true];
  }
  if(!array_key_exists('navigation', $blueprintArray)) {
    $blueprintArray['navigation'] = ['status' => 'all', 'template' => 'all'];
  }

  if(Str::startsWith($name, 'module.') === false) {
    $name = 'module.' . $name;
  }

  // Add to registry
  $registry['blueprints']['pages/' . $name] = $blueprintArray;
  $registry['templates'][$name] = $templatePath;
  $registry['pageModels'][$name] = option('medienbaecker.modules.model', 'ModulePage');

  return $registry;

}