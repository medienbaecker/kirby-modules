<?php

namespace Medienbaecker\Modules;

use Kirby\Panel\PageCreateDialog;

/**
 * Custom create dialog that adds a template picker
 * and reframes the slug field as an optional anchor
 */
class ModuleCreateDialog
{
  public static function load(): array
  {
    $request = kirby()->request();

    $hasModuleBlueprints = false;
    foreach (ModuleRegistry::create()['blueprints'] as $blueprint => $_) {
      if (str_starts_with($blueprint, 'pages/module.')) {
        $hasModuleBlueprints = true;
        break;
      }
    }
    if (!$hasModuleBlueprints) {
      throw new \Kirby\Exception\NotFoundException(t('modules.create.error.notemplates'));
    }

    $result = (new PageCreateDialog(
      parentId: $request->get('parent'),
      sectionId: $request->get('section'),
      template: $request->get('template'),
      viewId: $request->get('view'),
      slug: $request->get('slug'),
      title: $request->get('title'),
      uuid: $request->get('uuid'),
    ))->load();

    // Use custom Vue component with template picker
    $result['component'] = 'k-module-create-dialog';

    // Turn slug into an optional anchor field with # prefix
    $slug = ModuleRegistry::generateSlug(
      $request->get('parent') ?? '',
      $result['props']['template'] ?? ''
    );
    if ($slug) {
      $result['props']['fields']['slug']['placeholder'] = $slug;
      $result['props']['fields']['slug']['required'] = false;
      $result['props']['fields']['slug']['label'] = t('modules.create.anchor');
      $result['props']['fields']['slug']['icon'] = false;
      $result['props']['fields']['slug']['before'] = '#';
      $result['props']['fields']['slug']['help'] = false;
    }

    return $result;
  }

  public static function submit(): array
  {
    $input = kirby()->request()->body()->toArray();

    // Auto-generate slug if left empty
    if (empty($input['slug'])) {
      $input['slug'] = ModuleRegistry::generateSlug(
        $input['parent'] ?? '',
        $input['template'] ?? ''
      );
    }

    return (new PageCreateDialog(
      parentId: $input['parent'] ?? null,
      sectionId: $input['section'] ?? null,
      template: $input['template'] ?? null,
      viewId: $input['view'] ?? null,
    ))->submit($input);
  }
}
