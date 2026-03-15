<?php

use Kirby\Exception\InvalidArgumentException;
use Medienbaecker\Modules\ModuleCreateDialog;
use Medienbaecker\Modules\ModuleChangeTypeDialog;
use Medienbaecker\Modules\ModuleChangeSlugDialog;
use Medienbaecker\Modules\ModulesLicense;

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
        ],
        'modules/change-slug' => [
          'pattern' => 'modules/change-slug',
          'load' => fn() => ModuleChangeSlugDialog::load(),
          'submit' => fn() => ModuleChangeSlugDialog::submit(),
        ]
      ]
    ];
  },
  'system' => function () {
    return [
      'dialogs' => [
        'modules/remove-license' => [
          'load' => function () {
            return [
              'component' => 'k-remove-dialog',
              'props' => [
                'text' => t('modules.license.remove.text'),
                'submitButton' => [
                  'icon' => 'trash',
                  'text' => t('modules.license.remove.submit'),
                  'theme' => 'negative'
                ]
              ]
            ];
          },
          'submit' => function () {
            ModulesLicense::remove();
            return ['redirect' => 'system'];
          }
        ],
        'modules/activate' => [
          'load' => function () {
            $key = ModulesLicense::readKey();

            if ($key) {
              $version = kirby()->plugin('medienbaecker/modules')->version();
              return [
                'component' => 'k-modules-license-dialog',
                'props' => [
                  'license' => [
                    'code'    => $key,
                    'version' => $version ? 'v' . $version : null,
                  ],
                  'cancelButton' => false,
                  'submitButton' => [
                    'icon'   => 'open',
                    'text'   => t('modules.license.portal'),
                    'theme'  => 'info',
                    'link'   => ModulesLicense::PORTAL_URL,
                    'target' => '_blank'
                  ]
                ]
              ];
            }

            return [
              'component' => 'k-form-dialog',
              'props' => [
                'fields' => [
                  'info' => [
                    'type' => 'info',
                    'text' => tt('modules.license.info', ['url' => ModulesLicense::BUY_URL])
                  ],
                  'key' => [
                    'label' => t('modules.license.key'),
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'MODULES-0A550468-F0BB-4894-A833-F056AC38CE98',
                    'help' => t('modules.license.key.help')
                  ]
                ],
                'submitButton' => [
                  'icon' => 'check',
                  'text' => t('modules.license.submit'),
                  'theme' => 'love'
                ]
              ]
            ];
          },
          'submit' => function () {
            $key = kirby()->request()->get('key');

            if (empty($key)) {
              throw new InvalidArgumentException(t('modules.license.error.empty'));
            }

            if (!ModulesLicense::activate($key)) {
              throw new InvalidArgumentException(t('modules.license.error.invalid'));
            }

            return ['redirect' => 'system'];
          }
        ]
      ]
    ];
  }
];
