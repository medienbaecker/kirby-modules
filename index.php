<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App as Kirby;
use Medienbaecker\Modules\ModuleRegistry;

$moduleRegistry = ModuleRegistry::create();

Kirby::plugin('medienbaecker/modules', [
  'templates'    => $moduleRegistry['templates'],
  'snippets'     => $moduleRegistry['snippets'],
  'pageModels'   => $moduleRegistry['pageModels'],
  'blueprints'   => $moduleRegistry['blueprints'],
  'sections'     => [
    'modules' => require __DIR__ . '/lib/sections/modules.php'
  ],
  'hooks'        => require __DIR__ . '/lib/hooks.php',
  'pageMethods'  => require __DIR__ . '/lib/page-methods.php',
  'areas'        => require __DIR__ . '/lib/areas.php',
  'api'          => [
    'routes' => require __DIR__ . '/lib/routes.php',
  ],
  'translations' => require __DIR__ . '/lib/translations.php',
  'commands'     => require __DIR__ . '/lib/commands.php',
]);
