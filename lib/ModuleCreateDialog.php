<?php

namespace Medienbaecker\Modules;

use Kirby\Exception\NotFoundException;
use Kirby\Panel\PageCreateDialog;

class ModuleCreateDialog extends PageCreateDialog
{
  // Dialog routes call for() with the route arguments (none here); the
  // dialog parameters travel as request data, exactly like the core
  // page.create dialog.
  public static function for(): static
  {
    $request = kirby()->request();

    return new static(
      parentId: $request->get('parent'),
      sectionId: $request->get('section'),
      template: $request->get('template'),
      viewId: $request->get('view'),
      slug: $request->get('slug'),
      title: $request->get('title'),
      uuid: $request->get('uuid'),
    );
  }

  public function blueprints(): array
  {
    return array_map(
      fn(array $blueprint) => [...$blueprint, ...ModuleRegistry::typeVisuals($blueprint['name'])],
      parent::blueprints()
    );
  }

  public function load(): array
  {
    if (!$this->hasModuleBlueprints()) {
      throw new NotFoundException(t('modules.create.error.notemplates'));
    }

    $result = parent::load();
    $result['component'] = 'k-module-create-dialog';

    $status = option('medienbaecker.modules.autopublish', false) === true
      ? t('modules.visible')
      : t('modules.hidden');
    $result['props']['submitButton'] = tt('page.create', ['status' => $status]);

    $slug = ModuleRegistry::generateSlug(
      $this->parentId,
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

  public function submit(array|null $input = null): array
  {
    $input ??= kirby()->request()->body()->toArray();

    if (empty($input['slug'])) {
      $input['slug'] = ModuleRegistry::generateSlug(
        $input['parent'] ?? '',
        $input['template'] ?? ''
      );
    }

    $response = parent::submit($input);

    if (option('medienbaecker.modules.autopublish', false) !== true) {
      $page = $this->parent->find($input['slug']);
      if ($page) {
        kirby()->impersonate(
          'kirby',
          fn() => $page->update(['hidden' => 'true'], kirby()->defaultLanguage()?->code())
        );
      }
    }

    return $response;
  }

  private function hasModuleBlueprints(): bool
  {
    foreach (ModuleRegistry::load()['blueprints'] as $name => $props) {
      if (str_starts_with($name, 'pages/module.')) {
        return true;
      }
    }
    return false;
  }
}
