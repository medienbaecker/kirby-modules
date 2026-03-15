<?php

return [

  // Redirect container pages to their parent in the Panel
  'panel.route:before' => function ($route, $path, $method) {
    if ($path && str_starts_with($path, 'pages/')) {
      $pageId = str_replace('+', '/', substr($path, 6));
      if ($page = kirby()->page($pageId)) {
        if ($page->intendedTemplate()->name() === 'modules') {
          \Kirby\Panel\Panel::go($page->parent()->panel()->url());
        }
      }
    }
    return $route;
  },

  // Validate module moves
  'page.move:before' => function ($page, $parent) {
    if (!$page->isModule()) return;

    // Find the modules section whose container matches the move target
    $grandparent = $parent->parent();
    $sections = $grandparent?->blueprint()->sections() ?? [];

    $targetSection = null;
    foreach ($sections as $section) {
      if ($section->type() === 'modules' && $section->name() === $parent->slug()) {
        $targetSection = $section;
        break;
      }
    }

    $allowed = $targetSection?->templates() ?? [];
    if ($allowed && !in_array($page->intendedTemplate()->name(), $allowed)) {
      throw new \Kirby\Exception\PermissionException(t('modules.move.notallowed'));
    }
  }
];
