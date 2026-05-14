<?php

use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\I18n;
use Medienbaecker\Modules\ModuleCreateDialog;
use Medienbaecker\Modules\ModuleChangeTypeDialog;
use Medienbaecker\Modules\ModuleChangeSlugDialog;
use Medienbaecker\Modules\ModuleSectionApi;
use Medienbaecker\Modules\ModulesLicense;

$resolveModule = function (string $id): Page {
  $page = kirby()->page(str_replace('+', '/', $id));
  if (!$page || !$page->isModule()) {
    throw new NotFoundException('Module not found');
  }
  return $page;
};

return [
  'modules' => function () use ($resolveModule) {
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
        ],
        'modules/visibility' => [
          'pattern' => 'pages/(:any)/visibility',
          'load' => function (string $id) use ($resolveModule) {
            $page = $resolveModule($id);
            $language = kirby()->defaultLanguage()?->code();
            $hidden = $page->content($language)->hidden()->toBool();

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
                  'visibility' => $hidden ? 'hidden' : 'visible',
                ],
              ],
            ];
          },
          'submit' => function (string $id) use ($resolveModule) {
            $page = $resolveModule($id);
            $next = kirby()->request()->get('visibility') === 'hidden';
            $language = kirby()->defaultLanguage()?->code();
            $current = $page->content($language)->hidden()->toBool();

            if ($next !== $current) {
              ModuleSectionApi::flipHidden($page);
            }

            return ['event' => 'page.update'];
          },
        ],
      ],
      'buttons' => [
        'modules.visibility' => function (Page $page) {
          if (!$page->isModule()) return null;

          $language = kirby()->defaultLanguage()?->code();
          $hidden = $page->content($language)->hidden()->toBool();
          $disabled = $page->permissions()->cannot('update');

          $label = $hidden ? I18n::translate('modules.hidden') : I18n::translate('modules.visible');
          $title = I18n::translate('modules.visibility') . ': ' . $label;
          if ($disabled) {
            $title .= ' (' . I18n::translate('disabled') . ')';
          }

          return [
            'dialog'   => $page->panel()->url(true) . '/visibility',
            'disabled' => $disabled,
            'icon'     => $hidden ? 'hidden' : 'preview',
            'text'     => $label,
            'theme'    => $hidden ? 'negative-icon' : 'positive-icon',
            'title'    => $title,
          ];
        },
      ],
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
