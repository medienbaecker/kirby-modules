<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Content\LockedContentException;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\Dir;
use Kirby\Form\Form;

class ModuleSectionApi
{
  public static function routes(): array
  {
    // Closures get Closure::call($apiInstance), rebinding `self::` to Kirby's
    // Api class — capture the class name as a string instead.
    $api = self::class;

    return [
      [
        'pattern' => 'fields/(:any)',
        'method'  => 'GET',
        'action'  => function (string $childId) use ($api) {
          $section = $this->section();
          return $api::loadFields($section->model()->find($section->name()), $childId);
        },
      ],
      [
        'pattern' => 'duplicate/(:any)',
        'method'  => 'POST',
        'action'  => function (string $childId) use ($api) {
          $section = $this->section();
          return $api::duplicate($section->model()->find($section->name()), $childId);
        },
      ],
      [
        'pattern' => 'sort',
        'method'  => 'POST',
        'action'  => function () use ($api) {
          $section = $this->section();
          $api::sort($section->model()->find($section->name()), $this->requestBody('ids'));
          return ['status' => 'ok'];
        },
      ],
      [
        'pattern' => 'deleteAll',
        'method'  => 'POST',
        'action'  => function () use ($api) {
          $section = $this->section();
          $api::deleteAll($section->model()->find($section->name()));
          return ['status' => 'ok'];
        },
      ],
      [
        'pattern' => 'toggle-visibility/(:any)',
        'method'  => 'POST',
        'action'  => function (string $childId) use ($api) {
          $section = $this->section();
          return $api::toggleVisibility($section->model()->find($section->name()), $childId);
        },
      ],
      [
        'pattern' => 'create-container',
        'method'  => 'POST',
        'action'  => function () use ($api) {
          $section = $this->section();
          $api::createContainer($section->model(), $section->name(), $section->headline());
          return ['status' => 'ok'];
        },
      ],
    ];
  }

  public static function resolveModule(string $childId): Page
  {
    $child = kirby()->page(str_replace('+', '/', $childId));
    if (!$child) {
      throw new NotFoundException('Module not found');
    }
    return $child;
  }

  public static function ensureNotLocked(Page $child): void
  {
    $lock = $child->lock();
    if ($lock?->isLocked()) {
      throw new LockedContentException($lock);
    }
  }

  // Same 404 as resolveModule() — a permission error would leak whether the
  // ID exists in another container.
  public static function assertChildOf(Page $child, ?Page $container): void
  {
    if (!$container || !$child->parent()?->is($container)) {
      throw new NotFoundException('Module not found');
    }
  }

  public static function loadFields(?Page $container, string $childId): array
  {
    $child = self::resolveModule($childId);
    self::assertChildOf($child, $container);

    $language = kirby()->language()?->code() ?? 'default';
    $form = $child->version('changes')->exists($language)
      ? Form::for($child, ['values' => $child->version('changes')->content($language)->toArray()])
      : Form::for($child);

    $fields = $form->fields();

    return [
      'fields' => $fields->toProps(),
      // Blueprint values only — stripping passthrough (`hidden`, `uuid`, `lock`)
      // keeps out-of-band content state from riding back through /changes/save.
      'values' => array_diff_key($fields->toFormValues(), $fields->passthrough()),
    ];
  }

  public static function duplicate(?Page $container, string $childId): array
  {
    $child = self::resolveModule($childId);
    self::assertChildOf($child, $container);

    // Kirby's default slug appends a locale suffix (-copy / -kopie / …) and
    // collides on the second duplicate.
    $slug = ModuleRegistry::duplicateSlug(
      $child->parent()->id(),
      $child->slug()
    );
    $duplicate = $child->duplicate($slug, ['files' => true]);

    // Skip the _changes copy if another user holds the lock — their `Lock:`
    // field would otherwise be cloned into the duplicate.
    $changesDir = $child->root() . '/_changes';
    if (is_dir($changesDir) && !$child->lock()?->isLocked()) {
      Dir::copy($changesDir, $duplicate->root() . '/_changes');
    }

    // A hidden source always duplicates as hidden; autopublish only decides
    // what happens when the source was visible.
    $language = kirby()->defaultLanguage()?->code();
    $hidden = $child->content($language)->hidden()->toBool()
      || option('medienbaecker.modules.autopublish', false) !== true;
    // Re-assign $duplicate after each call: changeStatus and update move the
    // previous instance to immutable storage.
    kirby()->impersonate('kirby', function () use (&$duplicate, $child, $hidden, $language) {
      $duplicate = $duplicate->changeStatus('listed', $child->num() + 1);
      $duplicate = self::writeHidden($duplicate, $hidden ? 'true' : null, $language);
    });

    return ['status' => 'ok'];
  }

  public static function sort(?Page $container, array $ids): void
  {
    if (!$container) return;

    kirby()->impersonate('kirby', function () use ($ids, $container) {
      $num = 1;
      foreach ($ids as $id) {
        $page = kirby()->page($id);
        if (!$page || !$page->parent()?->is($container)) continue;
        $page->changeStatus('listed', $num++);
      }
    });
  }

  public static function deleteAll(?Page $container): void
  {
    if (!$container) return;

    // Snapshot ids so the delete loop doesn't iterate over a children
    // collection that the deletes themselves mutate.
    $ids = $container->children()->keys();
    foreach ($ids as $id) {
      $child = kirby()->page($id);
      if ($child) self::ensureNotLocked($child);
    }
    foreach ($ids as $id) {
      $child = kirby()->page($id);
      $child?->delete(true);
    }
  }

  public static function toggleVisibility(?Page $container, string $childId): array
  {
    $child = self::resolveModule($childId);
    self::assertChildOf($child, $container);
    self::flipHidden($child);
    return ['status' => 'ok'];
  }

  public static function flipHidden(Page $child): bool
  {
    self::ensureNotLocked($child);
    $language = kirby()->defaultLanguage()?->code();
    $hidden = $child->content($language)->hidden()->toBool();
    self::writeHidden($child, $hidden ? null : 'true', $language);
    return !$hidden;
  }

  // Mirror to _changes too — Version::publish overwrites latest with the
  // changes content, so a pending publish would otherwise undo the toggle.
  private static function writeHidden(Page $child, ?string $value, ?string $language): Page
  {
    $language ??= 'default';
    // Re-assign $child: update() moves the previous instance to immutable storage.
    $child = $child->update(['hidden' => $value], $language);

    $changes = $child->version('changes');
    if ($changes->exists($language)) {
      $changes->save(['hidden' => $value], $language);
    }

    return $child;
  }

  public static function createContainer(ModelWithContent $model, string $slug, string $headline): void
  {
    if ($model->find($slug)) return;

    kirby()->impersonate('kirby', fn() => $model->createChild([
      'content'  => ['title' => $headline],
      'slug'     => $slug,
      'template' => 'modules',
    ])->publish());
  }
}
