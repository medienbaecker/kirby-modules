<?php

namespace Medienbaecker\Modules;

class ModuleChangeTypeDialog
{
  public static function load(): array
  {
    $pageId = kirby()->request()->get('page');
    $page = kirby()->page(str_replace('+', '/', $pageId));

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
    $page->changeTemplate($input['template']);
    return true;
  }
}
