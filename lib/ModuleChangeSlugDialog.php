<?php

namespace Medienbaecker\Modules;

use Kirby\Exception\DuplicateException;

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

  public function submit(): array
  {
    try {
      $this->module->changeSlug(kirby()->request()->body()->get('slug'));
    } catch (DuplicateException $e) {
      throw new DuplicateException(tt('modules.changeAnchor.duplicate', ['anchor' => $e->getData()['slug']]));
    }
    return ['event' => 'model.update'];
  }
}
