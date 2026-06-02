<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Find;
use Kirby\Panel\PageCreateDialog;

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

    $result['component'] = 'k-module-create-dialog';

    $result['props']['blueprints'] = array_map(
      fn(array $blueprint) => [...$blueprint, ...ModuleRegistry::typeVisuals($blueprint['name'])],
      $result['props']['blueprints']
    );

    $status = option('medienbaecker.modules.autopublish', false) === true
      ? t('modules.visible')
      : t('modules.hidden');
    $result['props']['submitButton'] = tt('page.create', ['status' => $status]);

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

    $response = (new PageCreateDialog(
      parentId: $input['parent'] ?? null,
      sectionId: $input['section'] ?? null,
      template: $input['template'] ?? null,
      viewId: $input['view'] ?? null,
    ))->submit($input);

    if (option('medienbaecker.modules.autopublish', false) !== true) {
      $parent = Find::parent($input['parent'] ?? 'site');
      $page = $parent->find($input['slug']);
      if ($page) {
        kirby()->impersonate(
          'kirby',
          fn() => $page->update(['hidden' => 'true'], kirby()->defaultLanguage()?->code())
        );
      }
    }

    return $response;
  }
}
