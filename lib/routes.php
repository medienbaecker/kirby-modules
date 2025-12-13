<?php

use Kirby\Toolkit\I18n;

return function ($kirby) {
  return [[
    'pattern' => 'pages/(:any)/modules',
    'method'  => 'POST',
    'action'  => function (string $id) use ($kirby) {
      $page = $this->page($id);

      if (!$page?->hasModules() || $page->find('modules')) {
        return ['status' => 'ok', 'created' => false];
      }

      $kirby->impersonate('kirby');
      $page->createChild([
        'content'  => ['title' => I18n::translate('modules')],
        'slug'     => 'modules',
        'template' => 'modules',
      ])->publish();

      return ['status' => 'ok', 'created' => true];
    }
  ]];
};
