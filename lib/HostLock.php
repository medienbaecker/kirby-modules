<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Content\LockedContentException;

/**
 * Kirby's content lock is per model, so module edits wouldn't lock the
 * page they belong to. This class mirrors module changes as a changes
 * version with identical content on the host page: no form diff, but
 * the file carries the editor's lock, giving the whole page Kirby's
 * native lock UI and attribution. Publishing or discarding the host
 * deletes the mirror inside core (Changes::save() drops no-diff
 * versions); sync() covers the remaining cases.
 */
class HostLock
{
  // The page or site whose Panel view hosts the module's section
  public static function hostOf(Page $module): Page|Site|null
  {
    $container = $module->parent();
    if (!$container?->isModuleContainer()) {
      return null;
    }
    return $container->parentModel();
  }

  public static function ensureUnlocked(Page|Site $host): void
  {
    $lock = $host->lock();
    if ($lock?->isLocked()) {
      throw new LockedContentException($lock);
    }
  }

  // Creates, refreshes or removes the mirror, depending on whether any
  // module below the host still has unsaved changes.
  public static function sync(Page|Site $host, ?string $language = null): void
  {
    $language ??= App::instance()->language()?->code() ?? 'default';
    $changes = $host->version('changes');
    $latest = $host->version('latest');

    if (static::hasPendingModules($host, $language)) {
      // The save refreshes the lock's mtime and owner in every case
      $content = $changes->exists($language)
        ? $changes->content($language)
        : $latest->content($language);
      $changes->save($content->toArray(), $language);
      return;
    }

    if (!$changes->exists($language)) {
      return;
    }

    // The identity check spares real unsaved field changes. isIdentical
    // reads raw storage and can't succeed while the language has no
    // translation file yet — compare the fallback content instead,
    // ignoring lock and uuid like core does.
    $strip = fn(array $fields) => array_diff_key($fields, ['lock' => null, 'uuid' => null]);
    $identical = $latest->exists($language)
      ? $changes->isIdentical($latest, $language)
      : $strip($changes->content($language)->toArray()) == $strip($latest->content($language)->toArray());

    if ($identical) {
      $changes->delete($language);
    }
  }

  // Delete and move affect the changes of every language
  public static function syncAll(Page|Site $host): void
  {
    $languages = App::instance()->languages()->values()
      ? array_map(fn($lang) => $lang->code(), App::instance()->languages()->values())
      : ['default'];

    foreach ($languages as $code) {
      static::sync($host, $code);
    }
  }

  public static function hasPendingModules(Page|Site $host, string $language): bool
  {
    foreach ($host->children() as $container) {
      if (!$container->isModuleContainer()) {
        continue;
      }
      foreach ($container->children() as $module) {
        if ($module->version('changes')->exists($language)) {
          return true;
        }
      }
    }
    return false;
  }

  // Resolves a module from a path like `api/pages/a+modules+b/changes/save`
  public static function moduleFromApiPath(string $path): ?Page
  {
    $api = App::instance()->option('api.slug', 'api');
    $pattern = '!^' . preg_quote($api, '!') . '/pages/([^/]+)/changes/(?:save|discard|publish)$!';

    if (!preg_match($pattern, $path, $matches)) {
      return null;
    }

    $page = App::instance()->page(str_replace('+', '/', $matches[1]));
    return $page?->isModule() ? $page : null;
  }

}
