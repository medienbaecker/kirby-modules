<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Blueprint;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Section;
use Kirby\Content\LockedContentException;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
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
        'pattern' => 'fields',
        'method'  => 'POST',
        'action'  => function () {
          $container = ModuleSectionRoutes::container($this->section());
          return ModuleSectionRoutes::loadFieldsBatch($container, $this->requestBody('ids'));
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
        'pattern' => 'move',
        'method'  => 'POST',
        'action'  => function () {
          $section = $this->section();
          return ModuleSectionRoutes::move(
            ModuleSectionRoutes::container($section),
            $this->requestBody('id'),
            $this->requestBody('ids')
          );
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

  public static function ensureModuleAndHostUnlocked(Page $child): void
  {
    $lock = $child->lock();
    if ($lock?->isLocked()) {
      throw new LockedContentException($lock);
    }
    if ($host = HostLock::hostOf($child)) {
      HostLock::ensureUnlocked($host);
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

  public static function assertSiblingOf(Page $child, ?Page $container): void
  {
    $source = $child->parent();

    if (
      !$container ||
      $source?->intendedTemplate()->name() !== 'modules' ||
      $source->parentModel()->is($container->parentModel()) === false
    ) {
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
      // Blueprint values only - stripping passthrough (`hidden`, `uuid`, `lock`)
      // keeps out-of-band content state from riding back through /changes/save.
      'values' => array_diff_key($fields->toFormValues(), $fields->passthrough()),
      // `label` can derive from fields; the client refreshes it from here after
      // a save, sparing a full section refetch.
      'moduleName' => (string) $child->title(),
    ];
  }

  // Fields for many modules in one request: an extreme page would otherwise
  // boot Kirby once per module. A bad id fails only its own entry.
  public static function loadFieldsBatch(?Page $container, array $ids): array
  {
    $result = [];
    foreach ($ids as $childId) {
      try {
        $result[$childId] = self::loadFields($container, $childId);
      } catch (\Throwable $e) {
        $result[$childId] = ['error' => true];
      }
    }
    return $result;
  }

  public static function duplicate(?Page $container, string $childId): array
  {
    $child = self::resolveModule($childId);
    self::assertChildOf($child, $container);
    HostLock::ensureUnlocked($container->parentModel());

    // Kirby's default slug appends a locale suffix (-copy / -kopie / …) and
    // collides on the second duplicate.
    $slug = ModuleRegistry::duplicateSlug(
      $child->parent()->id(),
      $child->slug()
    );
    $duplicate = $child->duplicate($slug, ['files' => true]);

    // A hidden source always duplicates as hidden; autopublish only decides
    // what happens when the source was visible.
    $language = kirby()->defaultLanguage()?->code();
    $hidden = $child->isHidden()
      || self::isInvalid($child)
      || !self::shouldAutopublish($child->blueprint(), $container);
    $duplicate = kirby()->impersonate(
      'kirby',
      fn() => self::writeHidden($duplicate, $hidden ? 'true' : null, $language)
    );
    $duplicate = self::promote($duplicate, $child->num() + 1);

    // Carry pending changes via the Version API, not a raw directory copy:
    // raw files carry the source's `Uuid:` and `Lock:`. Skipped when another
    // user holds the lock.
    if (!$child->lock()?->isLocked()) {
      $sourceChanges = $child->version('changes');
      $codes = kirby()->multilang()
        ? kirby()->languages()->codes()
        : ['default'];
      foreach ($codes as $code) {
        if (!$sourceChanges->exists($code)) continue;
        $fields = $sourceChanges->read($code) ?? [];
        // Pin the copy's fresh uuid: publishing replaces latest wholesale.
        if ($uuid = $duplicate->content($code)->get('uuid')->value()) {
          $fields['uuid'] = $uuid;
        } else {
          unset($fields['uuid']);
        }
        if ($code === ($language ?? 'default')) {
          if ($hidden) {
            $fields['hidden'] = 'true';
          } else {
            unset($fields['hidden']);
          }
        }
        $duplicate->version('changes')->create($fields, $code);
      }
    }

    // The copied changes version may add pending changes to the host.
    HostLock::sync($container->parentModel());

    return ['status' => 'ok'];
  }

  public static function sort(?Page $container, array $ids): void
  {
    if (!$container) return;
    HostLock::ensureUnlocked($container->parentModel());

    kirby()->impersonate('kirby', function () use ($ids, $container) {
      $num = 1;
      foreach ($ids as $id) {
        $page = kirby()->page($id);
        if (!$page || !$page->parent()?->is($container)) continue;
        $page->changeStatus('listed', $num++);
      }
    });
  }

  public static function move(?Page $container, string $childId, array $ids): array
  {
    $child = self::resolveModule($childId);

    if ($container && $child->parent()?->is($container)) {
      self::sort($container, $ids);
      return ['status' => 'ok', 'id' => $child->id()];
    }

    self::assertSiblingOf($child, $container);
    self::ensureModuleAndHostUnlocked($child);

    $source = $child->parent();

    $moved = kirby()->impersonate('kirby', function () use ($child, $container) {
      // Without the default language this only sets a URL key, not the folder name.
      $slug = ModuleRegistry::uniqueSlug($container->id(), $child->slug());

      if ($slug !== null && $slug !== $child->slug()) {
        $child = $child->changeSlug($slug, kirby()->defaultLanguage()?->code());
      }

      return $child->move($container);
    });

    self::sort($container, array_map(
      fn($id) => $id === $childId ? $moved->id() : $id,
      $ids
    ));

    $source->purge();
    self::sort($source, $source->children()->keys());

    return ['status' => 'ok', 'id' => $moved->id()];
  }

  public static function deleteAll(?Page $container): void
  {
    if (!$container) return;

    // Snapshot ids so the delete loop doesn't iterate over a children
    // collection that the deletes themselves mutate.
    $ids = $container->children()->keys();
    foreach ($ids as $id) {
      $child = kirby()->page($id);
      if ($child) self::ensureModuleAndHostUnlocked($child);
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
    self::ensureModuleAndHostUnlocked($child);
    $hidden = $child->isHidden();
    if ($hidden && $invalid = self::invalidLanguages($child)) {
      throw new InvalidArgumentException(
        message: self::invalidMessage($invalid)
      );
    }
    self::writeHidden($child, $hidden ? null : 'true', kirby()->defaultLanguage()?->code());
    return !$hidden;
  }

  private static function invalidMessage(array $codes): string
  {
    if (kirby()->multilang() === false) {
      return t('error.form.incomplete');
    }
    $names = array_map(fn($c) => kirby()->language($c)?->name() ?? $c, $codes);
    return tt('modules.visibility.invalid', ['languages' => implode(', ', $names)]);
  }

  // Mirror to _changes too — Version::publish overwrites latest with the
  // changes content, so a pending publish would otherwise undo the toggle.
  private static function writeHidden(Page $child, ?string $value, ?string $language): Page
  {
    $language ??= 'default';
    $writer = $child instanceof ModulePage ? $child->allowHiddenWrite() : $child;
    // Re-assign $child: update() moves the previous instance to immutable storage.
    $child = $writer->update(['hidden' => $value], $language);

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

  public static function shouldAutopublish(?Blueprint $blueprint, ?Page $container): bool
  {
    return self::asBool($blueprint?->autopublish())
      ?? self::sectionAutopublish($container)
      ?? option('medienbaecker.modules.autopublish', false) === true;
  }

  private static function sectionAutopublish(?Page $container): ?bool
  {
    if (!$container || !($host = $container->parentModel())) {
      return null;
    }
    try {
      $section = $host->blueprint()->section($container->slug());
    } catch (\Throwable) {
      return null;
    }
    return $section?->type() === 'modules' ? self::asBool($section->autopublish()) : null;
  }

  private static function asBool(mixed $value): ?bool
  {
    return is_bool($value) ? $value : null;
  }

  public static function applyAutopublish(Page $module): Page
  {
    if (
      self::shouldAutopublish($module->blueprint(), $module->parent())
      && !self::isInvalid($module)
    ) {
      return $module;
    }

    return kirby()->impersonate(
      'kirby',
      fn() => self::writeHidden($module, 'true', kirby()->defaultLanguage()?->code())
    );
  }

  public static function reconcileVisibility(Page $module): Page
  {
    if ($module->isHidden() || !self::isInvalid($module)) {
      return $module;
    }
    return kirby()->impersonate(
      'kirby',
      fn() => self::writeHidden($module, 'true', kirby()->defaultLanguage()?->code())
    );
  }

  public static function promote(Page $module, ?int $position = null): Page
  {
    return kirby()->impersonate(
      'kirby',
      fn() => $module->publish()->changeStatus('listed', $position)
    );
  }

  public static function isInvalid(Page $module): bool
  {
    return self::invalidLanguages($module) !== [];
  }

  public static function invalidLanguages(Page $module): array
  {
    $codes = kirby()->multilang() ? kirby()->languages()->codes() : ['current'];
    $fields = $module->blueprint()->fields();
    $invalid = [];
    foreach ($codes as $code) {
      $form = new Form(fields: $fields, model: $module, language: $code);
      $form->fill($module->content($code)->toArray());
      if ($form->errors() !== []) {
        $invalid[] = $code;
      }
    }
    return $invalid;
  }
}
