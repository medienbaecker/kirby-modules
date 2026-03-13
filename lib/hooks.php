<?php

return [
  'panel.route:before' => function ($route, $path, $method) {
    if ($path && preg_match('#^pages/(.+)\+modules$#', $path, $matches)) {
      $parentId = str_replace('+', '/', $matches[1]);
      if ($parent = kirby()->page($parentId)) {
        \Kirby\Panel\Panel::go($parent->panel()->url());
      }
    }
    return $route;
  },
  'page.move:before' => function ($page, $parent) {
    if (!$page->isModule()) return;

    $grandparent = $parent->parent();
    $section = $grandparent?->blueprint()->section('modules');
    $allowed = $section?->templates() ?? [];

    if ($allowed && !in_array($page->intendedTemplate()->name(), $allowed)) {
      throw new \Kirby\Exception\PermissionException(t('modules.move.notallowed'));
    }
  }
];
