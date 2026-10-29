<?php

use Kirby\Cms\Page;
use Kirby\Content\LockedContentException;
use Kirby\Exception\Exception;
use Kirby\Exception\PermissionException;
use Kirby\Http\Response;
use Kirby\Panel\Panel;
use Medienbaecker\Modules\HostLock;
use Medienbaecker\Modules\ModuleChangesCascade;
use Medienbaecker\Modules\ModulesField;

return [

  // Module changes operations get a 423 while another user holds the
  // host page's lock. The module's own lock can't cover that case.
  'route:before' => function ($path, $method) {
    if ($method !== 'POST') return;

    [$model, $action] = HostLock::changesRequest($path) ?? [null, null];
    if (!$model || $action === 'unlock') return;

    kirby()->setCurrentTranslation(kirby()->user()?->language() ?? kirby()->panelLanguage());

    try {
      if ($model instanceof Page && $model->isModule() && $host = HostLock::hostOf($model)) {
        HostLock::ensureUnlocked($host);
      }

      if ($action === 'publish') {
        ModuleChangesCascade::validate($model);
      }
    } catch (Exception $e) {
      $response = kirby()->api()->responseForException($e);
      echo Response::json($response, $response['code']);
      exit;
    }
  },

  'route:after' => function ($path, $method, $result) {
    if ($method !== 'POST' || ($result instanceof Response && $result->code() >= 400)) {
      return $result;
    }

    [$model, $action] = HostLock::changesRequest($path) ?? [null, null];
    if (!$model) return $result;

    // Separate from the sync below: sync re-locks the host as the
    // leaving user, which would undo the auto-unlock.
    if ($action === 'unlock') {
      try {
        HostLock::cascadeUnlock($model);
      } catch (Throwable) {
      }
      return $result;
    }

    $isModule = $model instanceof Page && $model->isModule();
    $host = $isModule ? HostLock::hostOf($model) : $model;

    try {
      if ($action === 'publish') ModuleChangesCascade::publish($model);
      if ($action === 'discard') ModuleChangesCascade::discard($model);
    } finally {
      try {
        if ($host && ($isModule || ModulesField::names($host) !== [])) {
          HostLock::sync($host);
        }
      } catch (Throwable) {
        // The mirror must never break the changes operation itself
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

    if (!$parent instanceof Page || !$parent->isModuleContainer()) {
      throw new PermissionException(t('modules.move.notallowed'));
    }

    $targetField = ModulesField::for($parent->parentModel(), $parent->slug());

    if (!$targetField) {
      throw new PermissionException(t('modules.move.notallowed'));
    }

    if (!in_array($page->intendedTemplate()->name(), $targetField->templates())) {
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
