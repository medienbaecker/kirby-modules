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

    // When the module's blueprint is missing, its page falls back to
    // pages/default which has no changeTemplate option — so $page->blueprints()
    // returns empty. Use the registry directly in that case so users can
    // still recover by switching to a real module type.
    $blueprints = $page->blueprints();
    if (empty($blueprints)) {
      foreach (ModuleRegistry::create()['blueprints'] as $name => $props) {
        if (!str_starts_with($name, 'pages/module.')) continue;
        $blueprints[] = [
          'name'  => str_replace('pages/', '', $name),
          'title' => $props['title'] ?? ucfirst(str_replace('pages/module.', '', $name)),
        ];
      }
    }

    $options = [];
    foreach ($blueprints as $bp) {
      $options[] = ['text' => $bp['title'], 'value' => $bp['name']];
    }

    // If the current template isn't in the allowed list (e.g. blueprint was
    // deleted), prepend it as a disabled option so the Select has a valid
    // current value and can be opened.
    $currentName = $page->intendedTemplate()->name();
    if (!in_array($currentName, array_column($options, 'value'), true)) {
      array_unshift($options, [
        'text'     => t('modules.missingTemplate') . ' (' . $currentName . ')',
        'value'    => $currentName,
        'disabled' => true,
      ]);
    }

    return [
      'component' => 'k-form-dialog',
      'props' => [
        'fields' => [
          'template' => [
            'type' => 'select',
            'label' => t('modules.create.type'),
            'options' => $options,
            'required' => true,
            'empty' => false,
          ]
        ],
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

  private static function resolveModule(): Page
  {
    $id = (string) kirby()->request()->get('page');
    $page = kirby()->page(str_replace('+', '/', $id));
    if (!$page || !$page->isModule()) {
      throw new NotFoundException('Module not found');
    }
    return $page;
  }

  // Guards the fallback rename path against path-traversal-style template names.
  private static function validateTarget(string $target): string
  {
    if (!ModuleRegistry::hasBlueprint($target)) {
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
