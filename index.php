<?php

// Composer-installed autoloader if available; otherwise use a simple
// PSR-4 fallback so the plugin works when bundled without composer.
@include_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function ($class) {
  $prefix = 'Medienbaecker\\Modules\\';
  if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
  $file = __DIR__ . '/lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
  if (is_file($file)) require $file;
});

use Kirby\Cms\App as Kirby;
use Kirby\Data\Json;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Medienbaecker\Modules\ModuleRegistry;

$moduleRegistry = ModuleRegistry::load();
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
      'createModule'  => $pageMethods['createModule'],
      'hasModules'    => $pageMethods['hasModules'],
      'filePool'      => $pageMethods['filePool'],
    ],
    'fileMethods'  => ['filePool' => $pageMethods['filePool']],
    'userMethods'  => ['filePool' => $pageMethods['filePool']],
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
