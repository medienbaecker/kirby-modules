<?php

namespace Medienbaecker\Modules;

/**
 * Dialog for changing a module's slug/anchor
 */
class ModuleChangeSlugDialog
{
  public static function load(): array
  {
    $pageId = kirby()->request()->get('page');
    $page = kirby()->page(str_replace('+', '/', $pageId));
    if (!$page) throw new \Kirby\Exception\NotFoundException('Module not found');

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
          'page' => $pageId,
          'slug' => $page->slug(),
        ],
        'submitButton' => t('change'),
      ]
    ];
  }

  public static function submit(): bool
  {
    $input = kirby()->request()->body()->toArray();
    $pageId = kirby()->request()->get('page');
    $page = kirby()->page(str_replace('+', '/', $pageId));
    if (!$page) throw new \Kirby\Exception\NotFoundException('Module not found');

    kirby()->impersonate('kirby', function () use ($page, $input) {
      $page->changeSlug($input['slug']);
    });

    return true;
  }
}
