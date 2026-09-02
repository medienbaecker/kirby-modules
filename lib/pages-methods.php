<?php

use Medienbaecker\Modules\ModulesCollection;

return [

  // Plural counterpart to $page->modules() for a whole collection of pages.
  'modules' => function (string $container = 'modules', bool $hidden = false) {
    $modules = new ModulesCollection;
    foreach ($this as $page) {
      foreach ($page->modules($container, $hidden) as $module) {
        $modules->append($module);
      }
    }
    return $modules;
  }
];
