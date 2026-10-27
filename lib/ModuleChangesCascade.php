<?php

namespace Medienbaecker\Modules;

use Generator;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

class ModuleChangesCascade
{
  private static function descendants(Page|Site $model): Generator
  {
    foreach (ModulesField::names($model) as $name) {
      $container = $model->find($name);
      if (!$container) continue;

      foreach ($container->children() as $child) {
        yield $child;
        yield from static::descendants($child);
      }
    }
  }

  public static function publish(Page|Site $model): void
  {
    foreach (static::descendants($model) as $child) {
      if ($child->guards()->isAvailable('update') === false) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current')) {
        $changes->publish(language: 'current');
      }
    }
  }

  public static function discard(Page|Site $model): void
  {
    foreach (static::descendants($model) as $child) {
      if ($child->guards()->isAvailable('update') === false) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current')) {
        $changes->delete('current');
      }
    }
  }
}
