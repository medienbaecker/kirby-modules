<?php

namespace Medienbaecker\Modules;

use Generator;
use Kirby\Cms\Page;

class ModuleChangesCascade
{
  private static function descendants(Page $module): Generator
  {
    foreach ($module->blueprint()->sections() as $section) {
      if ($section->type() !== 'modules') continue;
      $container = $module->find($section->name());
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
      if ($child->permissions()->can('update') !== true) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current')) {
        $changes->publish(language: 'current');
      }
    }
  }

  public static function discard(Page $module): void
  {
    foreach (static::descendants($module) as $child) {
      if ($child->permissions()->can('update') !== true) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current')) {
        $changes->delete('current');
      }
    }
  }
}
