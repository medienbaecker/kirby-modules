<?php

namespace Medienbaecker\Modules;

class ModuleChangeSlugDialog extends ModuleDialog
{
  public function load(): array
  {
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
          'slug' => $this->module->slug(),
        ],
        'submitButton' => t('change'),
      ]
    ];
  }

  public function submit(): bool
  {
    $this->module->changeSlug(kirby()->request()->body()->get('slug'));
    return true;
  }
}
