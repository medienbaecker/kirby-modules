<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;

class Module
{
  // Renders a module from code, mirroring Kirby's Block::factory:
  //
  //   echo Module::factory([
  //     'type' => 'contact',
  //     'content' => ['contact' => $contact->uuid()->value()]
  //   ])->toHtml();
  //
  // The type accepts short ('contact') or full ('module.contact') names.
  // The module renders with its regular snippet; `$page` inside the
  // snippet is the parent (the current page unless one is passed).
  public static function factory(array $props): Page
  {
    $template = ModuleRegistry::template($props['type'] ?? $props['template'] ?? null);

    return Page::factory([
      'slug'     => $props['slug'] ?? str_replace('module.', '', $template),
      'template' => $template,
      'parent'   => $props['parent'] ?? page(),
      'content'  => $props['content'] ?? [],
    ]);
  }
}
