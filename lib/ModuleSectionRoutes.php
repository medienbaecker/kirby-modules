<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Section;
use Kirby\Content\LockedContentException;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\Dir;
use Kirby\Form\Form;
use Kirby\Toolkit\Str;

class ModuleSectionRoutes
{
  // Kirby executes route actions with Closure::call($apiInstance), which
  // rebinds both $this and self:: — so the actions reference this class by
  // name and reach the section through the Api instance.
  public static function routes(): array
  {
    return [
      [
        'pattern' => 'fields/(:any)',
        'method'  => 'GET',
        'action'  => function (string $childId) {
          $container = ModuleSectionRoutes::container($this->section());
          return ModuleSectionRoutes::loadFields($container, $childId);
        },
      ],
      [
        'pattern' => 'duplicate/(:any)',
        'method'  => 'POST',
        'action'  => function (string $childId) {
          $container = ModuleSectionRoutes::container($this->section());
          return ModuleSectionRoutes::duplicate($container, $childId);
        },
      ],
      [
        'pattern' => 'sort',
        'method'  => 'POST',
        'action'  => function () {
          $container = ModuleSectionRoutes::container($this->section());
          ModuleSectionRoutes::sort($container, $this->requestBody('ids'));
          return ['status' => 'ok'];
        },
      ],
      [
        'pattern' => 'deleteAll',
        'method'  => 'POST',
        'action'  => function () {
          $container = ModuleSectionRoutes::container($this->section());
          ModuleSectionRoutes::deleteAll($container);
          return ['status' => 'ok'];
        },
      ],
      [
        'pattern' => 'toggle-visibility/(:any)',
        'method'  => 'POST',
        'action'  => function (string $childId) {
          $container = ModuleSectionRoutes::container($this->section());
          return ModuleSectionRoutes::toggleVisibility($container, $childId);
        },
      ],
      [
        'pattern' => 'create-container',
        'method'  => 'POST',
        'action'  => function () {
          $section = $this->section();
          ModuleSectionRoutes::createContainer($section->model(), $section->name(), $section->headline());
          return ['status' => 'ok'];
        },
      ],
    ];
  }

  // A section's container page shares the section's name as its slug.
  public static function container(Section $section): ?Page
  {
    return $section->model()->find($section->name());
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
    $hidden = $child->isHidden()
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
    $hidden = $child->isHidden();
    self::writeHidden($child, $hidden ? null : 'true', kirby()->defaultLanguage()?->code());
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

  public static function createContainer(ModelWithContent $model, string $slug, ?string $headline = null): Page
  {
    if ($container = $model->find($slug)) {
      return $container;
    }

    $headline ??= Str::ucfirst(str_replace('-', ' ', $slug));

    return kirby()->impersonate('kirby', fn() => $model->createChild([
      'content'  => ['title' => $headline],
      'slug'     => $slug,
      'template' => 'modules',
    ])->publish());
  }

  // Applies the autopublish option to a freshly created module.
  public static function applyAutopublish(Page $module): Page
  {
    if (option('medienbaecker.modules.autopublish', false) === true) {
      return $module;
    }

    return kirby()->impersonate(
      'kirby',
      fn() => self::writeHidden($module, 'true', kirby()->defaultLanguage()?->code())
    );
  }
}
