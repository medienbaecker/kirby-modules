<?php

namespace Medienbaecker\Modules;

/**
 * Dialog for changing a module's template type
 */
class ModuleChangeTypeDialog
{
  public static function load(): array
  {
    $pageId = kirby()->request()->get('page');
    $page = kirby()->page(str_replace('+', '/', $pageId));
    if (!$page) throw new \Kirby\Exception\NotFoundException('Module not found');

    // Build options from allowed templates (set by ModuleRegistry)
    $options = [];
    foreach ($page->blueprints() as $bp) {
      $options[] = ['text' => $bp['title'], 'value' => $bp['name']];
    }

    return [
      'component' => 'k-form-dialog',
      'props' => [
        'fields' => [
          'template' => [
            'type' => 'select',
            'label' => t('modules.create.type'),
            'options' => $options,
            'required' => true,
            'empty' => false,
          ]
        ],
        'value' => [
          // Page ID round-trips through form value (query params aren't sent on submit)
          'page' => $pageId,
          'template' => $page->intendedTemplate()->name()
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
    $page->changeTemplate($input['template']);
    return true;
  }
}
