<?php

namespace Medienbaecker\Modules;

use Kirby\Toolkit\I18n;

class ModuleVisibilityDialog extends ModuleDialog
{
  public function load(): array
  {
    return [
      'component' => 'k-form-dialog',
      'props' => [
        'fields' => [
          'visibility' => [
            'label'    => I18n::translate('modules.visibility'),
            'type'     => 'radio',
            'required' => true,
            'options'  => [
              ['value' => 'visible', 'text' => I18n::translate('modules.visible')],
              ['value' => 'hidden', 'text' => I18n::translate('modules.hidden')],
            ],
          ],
        ],
        'submitButton' => I18n::translate('change'),
        'value' => [
          'visibility' => $this->module->isHidden() ? 'hidden' : 'visible',
        ],
      ],
    ];
  }

  public function submit(): array
  {
    $hidden = kirby()->request()->get('visibility') === 'hidden';

    if ($hidden !== $this->module->isHidden()) {
      ModuleSectionRoutes::flipHidden($this->module);
    }

    return ['event' => 'page.update'];
  }
}
