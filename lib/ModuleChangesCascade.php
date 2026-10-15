<?php

namespace Medienbaecker\Modules;

use Generator;
use Kirby\Cms\Page;

class ModuleChangesCascade
{
  private static function descendants(Page $module): Generator
  {
    foreach (ModulesField::names($module) as $name) {
      $container = $module->find($name);
      if (!$container) continue;

      foreach ($container->children() as $child) {
        yield $child;
        yield from static::descendants($child);
      }
    }
  }

  public static function publish(Page $module): void
  {
    foreach (static::descendants($module) as $child) {
      if ($child->guards()->isAvailable('update') === false) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current')) {
        $changes->publish(language: 'current');
      }
    }
  }

  public static function discard(Page $module): void
  {
    foreach (static::descendants($module) as $child) {
      if ($child->guards()->isAvailable('update') === false) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current')) {
        $changes->delete('current');
      }
    }
  }
}
