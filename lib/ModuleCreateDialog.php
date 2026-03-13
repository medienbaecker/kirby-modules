<?php

namespace Medienbaecker\Modules;

use Kirby\Panel\PageCreateDialog;

class ModuleCreateDialog
{
  public static function load(): array
  {
    $request = kirby()->request();
    $result = (new PageCreateDialog(
      parentId: $request->get('parent'),
      sectionId: $request->get('section'),
      template: $request->get('template'),
      viewId: $request->get('view'),
      slug: $request->get('slug'),
      title: $request->get('title'),
      uuid: $request->get('uuid'),
    ))->load();
    $result['component'] = 'k-module-create-dialog';

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
