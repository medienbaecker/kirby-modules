<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App as Kirby;
use Kirby\Data\Json;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Medienbaecker\Modules\ModuleRegistry;

$moduleRegistry = ModuleRegistry::create();
$pageMethods = require __DIR__ . '/lib/page-methods.php';

Kirby::plugin(
  'medienbaecker/modules',
  license: fn($plugin) => new \Medienbaecker\Modules\ModulesLicense($plugin),
  extends: [
    'templates'    => $moduleRegistry['templates'],
    'snippets'     => $moduleRegistry['snippets'],
    'pageModels'   => $moduleRegistry['pageModels'],
    'blueprints'   => $moduleRegistry['blueprints'],
    'sections'     => [
      'modules' => require __DIR__ . '/lib/sections/modules.php'
    ],
    'hooks'        => require __DIR__ . '/lib/hooks.php',
    'pageMethods'  => $pageMethods,
    'siteMethods'  => [
      'modules'       => $pageMethods['modules'],
      'renderModules' => $pageMethods['renderModules'],
      'hasModules'    => $pageMethods['hasModules'],
    ],
    'areas'        => require __DIR__ . '/lib/areas.php',
    'commands'     => require __DIR__ . '/lib/commands.php',
    'translations' => A::keyBy(
      A::map(
        Dir::files(__DIR__ . '/translations'),
        function ($file) {
          $translations = [];
          foreach (Json::read(__DIR__ . '/translations/' . $file) as $key => $value) {
            $translations["modules.{$key}"] = $value;
          }
          return A::merge(['lang' => F::name($file)], $translations);
        }
      ),
      'lang'
    ),
  ]
);
