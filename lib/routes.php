<?php

return function ($kirby) {
  return [
    [
      'pattern' => 'pages/(:any)/modules',
      'method'  => 'POST',
      'action'  => function (string $id) use ($kirby) {
        $page = $this->page($id);

        if (!$page) {
          return false;
        }

        if (!$page->hasModules() || $page->find('modules')) {
          return [
            'status'  => 'ok',
            'created' => false,
          ];
        }
      
        try {
          $kirby->impersonate('kirby');
          $modulesPage = $page->createChild([
            'content'  => ['title' => I18n::translate('modules')],
            'slug'     => 'modules',
            'template' => 'modules',
          ]);

          if ($modulesPage) {
            $modulesPage->publish();
          }
  
          return [
            'status'  => 'ok',
            'created' => true,
          ];
        } catch (Exception $error) {
          throw new Exception($error);
        }
      }
    ],
  ];
};