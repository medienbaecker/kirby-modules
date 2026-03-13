<?php

use Medienbaecker\Modules\ModuleCreateDialog;
use Medienbaecker\Modules\ModuleChangeTypeDialog;
return [
  'modules' => function () {
    return [
      'dialogs' => [
        'modules/create' => [
          'pattern' => 'modules/create',
          'load' => fn() => ModuleCreateDialog::load(),
          'submit' => fn() => ModuleCreateDialog::submit(),
        ],
        'modules/change-type' => [
          'pattern' => 'modules/change-type',
          'load' => fn() => ModuleChangeTypeDialog::load(),
          'submit' => fn() => ModuleChangeTypeDialog::submit(),
        ]
      ]
    ];
  }
];
