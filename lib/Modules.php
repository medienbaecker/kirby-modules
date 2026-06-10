<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Toolkit\Str;

class Modules
{
  // Creates several modules from code, mirroring Kirby's Blocks::factory:
  //
  //   echo Modules::factory([
  //     ['type' => 'text', 'content' => ['textarea' => 'Hello']],
  //     ['type' => 'gallery', 'content' => ['images' => $images]],
  //   ])->toHtml();
  //
  // String keys become slugs. Duplicate slugs within the batch get a
  // numeric suffix (text, text-2, …) so no module silently drops out.
  public static function factory(array $modules, ?Page $parent = null): ModulesCollection
  {
    $collection = new ModulesCollection();

    foreach ($modules as $key => $props) {
      if (is_string($key)) {
        $props['slug'] ??= $key;
      }
      $props['parent'] ??= $parent;

      $module = Module::factory($props);

      while ($collection->get($module->id())) {
        $module = $module->clone(['slug' => Str::increment($module->slug(), '-', 2)]);
      }

      $collection->append($module);
    }

    return $collection;
  }
}
