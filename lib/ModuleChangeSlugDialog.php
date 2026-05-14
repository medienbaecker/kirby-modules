<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;

class ModuleChangeSlugDialog
{
  public static function load(): array
  {
    $page = self::resolveModule();

    return [
      'component' => 'k-form-dialog',
      'props' => [
        'fields' => [
          'slug' => [
            'type' => 'slug',
            'label' => t('modules.create.anchor'),
            'required' => true,
            'before' => '#',
            'icon' => false,
          ]
        ],
        'value' => [
          'page' => (string) kirby()->request()->get('page'),
          'slug' => $page->slug(),
        ],
        'submitButton' => t('change'),
      ]
    ];
  }

  public static function submit(): bool
  {
    $page = self::resolveModule();
    $page->changeSlug(kirby()->request()->body()->get('slug'));
    return true;
  }

  private static function resolveModule(): Page
  {
    $id = (string) kirby()->request()->get('page');
    $page = kirby()->page(str_replace('+', '/', $id));
    if (!$page || !$page->isModule()) {
      throw new NotFoundException('Module not found');
    }
    return $page;
  }
}
