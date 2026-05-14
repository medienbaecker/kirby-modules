<?php

return [

  'panel.route:before' => function ($route, $path, $method) {
    if ($path && str_starts_with($path, 'pages/')) {
      $pageId = str_replace('+', '/', substr($path, 6));
      if ($page = kirby()->page($pageId)) {
        if ($page->intendedTemplate()->name() === 'modules') {
          \Kirby\Panel\Panel::go($page->parentModel()->panel()->url());
        }
      }
    }
    return $route;
  },

  'page.move:before' => function ($page, $parent) {
    if (!$page->isModule()) return;

    $host = $parent->parentModel();
    $targetSection = null;
    foreach ($host->blueprint()->sections() as $section) {
      if ($section->type() === 'modules' && $section->name() === $parent->slug()) {
        $targetSection = $section;
        break;
      }
    }

    if (!$targetSection) {
      throw new \Kirby\Exception\PermissionException(t('modules.move.notallowed'));
    }

    $allowed = $targetSection->templates();
    if ($allowed && !in_array($page->intendedTemplate()->name(), $allowed)) {
      throw new \Kirby\Exception\PermissionException(t('modules.move.notallowed'));
    }
  },

  // Kirby's DELETE /api/pages/{id} route doesn't gate on the changes lock.
  'page.delete:before' => function ($page) {
    if (!$page->isModule()) return;
    $lock = $page->lock();
    if ($lock?->isLocked()) {
      throw new \Kirby\Content\LockedContentException($lock);
    }
  }
];
