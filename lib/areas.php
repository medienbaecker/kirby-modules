<?php

use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Medienbaecker\Modules\ModuleChangeSlugDialog;
use Medienbaecker\Modules\ModuleChangeTypeDialog;
use Medienbaecker\Modules\ModuleCreateDialog;
use Medienbaecker\Modules\ModulesLicense;
use Medienbaecker\Modules\ModuleVisibilityButton;
use Medienbaecker\Modules\ModuleVisibilityDialog;

return [
  'modules' => function ($kirby) {
    return [
      'dialogs' => [
        'modules/create' => [
          'controller' => ModuleCreateDialog::class,
        ],
        'modules/change-type' => [
          'pattern' => 'modules/change-type/(:any)',
          'controller' => ModuleChangeTypeDialog::class,
        ],
        'modules/change-slug' => [
          'pattern' => 'modules/change-slug/(:any)',
          'controller' => ModuleChangeSlugDialog::class,
        ],
        'modules/visibility' => [
          'pattern' => 'pages/(:any)/visibility',
          'controller' => ModuleVisibilityDialog::class,
        ],
      ],
      'buttons' => [
        'modules.visibility' => fn(Page $page) => $page->isModule()
          ? new ModuleVisibilityButton($page)
          : null,
      ],
    ];
  },
  'system' => function ($kirby) {
    return [
      'dialogs' => [
        'modules/remove-license' => [
          'load' => fn() => [
            'component' => 'k-remove-dialog',
            'props' => [
              'text' => t('modules.license.remove.text'),
              'submitButton' => [
                'icon' => 'trash',
                'text' => t('modules.license.remove.submit'),
                'theme' => 'negative'
              ]
            ]
          ],
          'submit' => function () {
            ModulesLicense::remove();
            return ['redirect' => 'system'];
          }
        ],
        'modules/activate' => [
          'load' => function () use ($kirby) {
            $key = ModulesLicense::readKey();

            if ($key) {
              $version = $kirby->plugin('medienbaecker/modules')->version();
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
          'submit' => function () use ($kirby) {
            $key = $kirby->request()->get('key');

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
