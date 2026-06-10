<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Exception\InvalidArgumentException;

class Modules
{
  // Creates virtual module pages from definitions keyed by slug:
  //
  //   Modules::factory([
  //     'banner' => [
  //       'template' => 'text',
  //       'content'  => ['textarea' => 'Hello'],
  //     ],
  //   ], $container);
  //
  // Template names may be short ('text') or full ('module.text'). A stable
  // uuid is derived from the container and slug unless the content provides
  // one, so UUIDs don't get regenerated on every request.
  public static function factory(array $modules, Page $parent): Pages
  {
    $props = [];
    foreach ($modules as $slug => $module) {
      if (empty($module['template'])) {
        throw new InvalidArgumentException('Virtual module "' . $slug . '" needs a template');
      }

      $content = $module['content'] ?? [];
      $content['uuid'] ??= 'virtual-' . str_replace('/', '-', $parent->id()) . '-' . $slug;

      $props[] = [
        ...$module,
        'slug'     => $slug,
        'template' => ModuleRegistry::qualify($module['template']),
        'content'  => $content,
      ];
    }

    return Pages::factory($props, $parent);
  }
}
