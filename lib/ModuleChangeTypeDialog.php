<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;

class ModuleChangeTypeDialog
{
  public static function load(): array
  {
    $page = self::resolveModule();

    // Source the list from the module's owning `modules` section so the order
    // and allowed types match the create dialog exactly (same section, same
    // `default`/`templates`/`templatesIgnore`). Fall back to the page's
    // changeTemplate option, then the registry, for edge/recovery cases.
    $blueprints = self::ownerSectionBlueprints($page) ?? $page->blueprints();
    if (empty($blueprints)) {
      foreach (ModuleRegistry::create()['blueprints'] as $name => $props) {
        if (!str_starts_with($name, 'pages/module.')) continue;
        $blueprints[] = [
          'name'  => str_replace('pages/', '', $name),
          'title' => $props['title'] ?? ucfirst(str_replace('pages/module.', '', $name)),
        ];
      }
    }

    $types = [];
    foreach ($blueprints as $blueprint) {
      $types[] = [
        'name'  => $blueprint['name'],
        'title' => $blueprint['title'],
        ...ModuleRegistry::typeVisuals($blueprint['name']),
      ];
    }

    // If the current type isn't in the list (blueprint deleted, or the section
    // doesn't allow it), prepend it as a disabled card so the grid still shows
    // what the module currently is and the dialog has a valid current value.
    $currentName = $page->intendedTemplate()->name();
    if (!in_array($currentName, array_column($types, 'name'), true)) {
      $currentProps = ModuleRegistry::create()['blueprints']['pages/' . $currentName] ?? null;
      array_unshift($types, [
        'name'     => $currentName,
        'title'    => $currentProps['title'] ?? t('modules.missingTemplate') . ' (' . $currentName . ')',
        ...ModuleRegistry::typeVisuals($currentName),
        'icon'     => $currentProps['icon'] ?? 'alert',
        'disabled' => true,
      ]);
    }

    return [
      'component' => 'k-module-change-type-dialog',
      'props' => [
        'blueprints' => $types,
        'value' => [
          // Page ID round-trips through form value (query params aren't sent on submit)
          'page' => (string) kirby()->request()->get('page'),
          'template' => $currentName
        ],
        'submitButton' => t('change'),
      ]
    ];
  }

  public static function submit(): bool
  {
    $page = self::resolveModule();
    $target = self::validateTarget(
      $page,
      (string) kirby()->request()->body()->get('template')
    );

    if (count($page->blueprints()) > 0) {
      $page->changeTemplate($target);
      return true;
    }

    // Missing-blueprint fallback: PageRules::changeTemplate would reject the
    // change because $page->blueprints() is empty. Rename files directly.
    kirby()->impersonate('kirby', fn() => static::renameTemplateFiles(
      $page->root(),
      $page->intendedTemplate()->name(),
      $target
    ));
    return true;
  }

  // A module's container slug equals its owning section's name on the host
  // page (see hooks.php). Reuse that section's blueprints() so change-type and
  // create produce an identical list. Fetch the one section by name rather than
  // iterating sections() — the latter also instantiates the host's other
  // sections (e.g. files), which can error outside a normal request.
  private static function ownerSectionBlueprints(Page $page): ?array
  {
    $container = $page->parent();
    $host = $container?->parentModel();
    if (!$host) {
      return null;
    }
    $section = $host->blueprint()->section($container->slug());
    if ($section && $section->type() === 'modules') {
      return $section->blueprints();
    }
    return null;
  }

  private static function resolveModule(): Page
  {
    $id = (string) kirby()->request()->get('page');
    $page = kirby()->page(str_replace('+', '/', $id));
    if (!$page || !$page->isModule()) {
      throw new NotFoundException('Module not found');
    }
    return $page;
  }

  // Restricts the target to a real module blueprint and, when the owning
  // section resolves, to the types that section allows.
  private static function validateTarget(Page $page, string $target): string
  {
    if (!ModuleRegistry::hasBlueprint($target)) {
      throw new InvalidArgumentException('Invalid module type');
    }
    $allowed = self::ownerSectionBlueprints($page);
    if ($allowed !== null && !in_array($target, array_column($allowed, 'name'), true)) {
      throw new InvalidArgumentException('Invalid module type');
    }
    return $target;
  }

  // Rename `module.<from>[.<lang>].txt` → `module.<to>[.<lang>].txt`
  // across a page root and its _changes/ subdir.
  private static function renameTemplateFiles(string $root, string $from, string $to): void
  {
    $pattern = '#^' . preg_quote($from) . '(\..+)?\.txt$#';
    foreach ([$root, $root . '/_changes'] as $dir) {
      if (!is_dir($dir)) continue;
      foreach (scandir($dir) as $file) {
        if (preg_match($pattern, $file, $m)) {
          rename($dir . '/' . $file, $dir . '/' . $to . ($m[1] ?? '') . '.txt');
        }
      }
    }
  }
}
