<?php

use Kirby\Content\LockedContentException;
use Kirby\Exception\PermissionException;
use Kirby\Panel\Panel;
use Medienbaecker\Modules\HostLock;

return [

  // Module changes operations get a 423 while another user holds the
  // host page's lock — the module's own lock can't cover that case.
  'route:before' => function ($path, $method) {
    if ($method !== 'POST' || !preg_match('!/changes/(save|discard|publish)$!', $path)) {
      return;
    }
    $module = HostLock::moduleFromApiPath($path);
    if ($module && $host = HostLock::hostOf($module)) {
      HostLock::ensureUnlocked($host);
    }
  },

  // Mirror every module changes operation onto the host page (see HostLock)
  'route:after' => function ($path, $method, $result) {
    if ($method === 'POST' && $module = HostLock::moduleFromApiPath($path)) {
      try {
        if ($host = HostLock::hostOf($module)) {
          HostLock::sync($host);
        }
      } catch (Throwable) {
        // The mirror must never break the module operation itself
      }
    }
    return $result;
  },

  'page.delete:after' => function ($page) {
    if (!$page->isModule()) return;
    if ($host = HostLock::hostOf($page)) {
      try {
        HostLock::syncAll($host);
      } catch (Throwable) {
      }
    }
  },

  'page.move:after' => function ($newPage, $oldPage) {
    if (!$newPage->isModule()) return;
    foreach ([HostLock::hostOf($oldPage), HostLock::hostOf($newPage)] as $host) {
      try {
        if ($host) HostLock::syncAll($host);
      } catch (Throwable) {
      }
    }
  },


  // Modules container pages have no Panel view of their own; jump to the
  // host page instead.
  'panel.route:before' => function ($route, $path, $method) {
    if (!$path || !str_starts_with($path, 'pages/')) {
      return $route;
    }
    $page = kirby()->page(str_replace('+', '/', substr($path, 6)));
    if ($page?->isModuleContainer()) {
      Panel::go($page->parentModel()->panel()->url());
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
      throw new PermissionException(t('modules.move.notallowed'));
    }

    $allowed = $targetSection->templates();
    if ($allowed && !in_array($page->intendedTemplate()->name(), $allowed)) {
      throw new PermissionException(t('modules.move.notallowed'));
    }
  },

  // Kirby's DELETE /api/pages/{id} route doesn't gate on the changes lock.
  'page.delete:before' => function ($page) {
    if (!$page->isModule()) return;
    $lock = $page->lock();
    if ($lock?->isLocked()) {
      throw new LockedContentException($lock);
    }
    if ($host = HostLock::hostOf($page)) {
      HostLock::ensureUnlocked($host);
    }
  }
];
