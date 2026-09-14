<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Form\Form;
use Kirby\Panel\Field;
use Kirby\Panel\PageCreateDialog;
use Kirby\Panel\Panel;
use Kirby\Toolkit\Str;
use Kirby\Uuid\Uuids;

class ModuleCreateDialog extends PageCreateDialog
{
  // Field names the create dialog never renders (the page's routing fields;
  // `title` is the type name). Shared by customFields() and guardListedFields()
  // so the skip and the guard can't drift apart.
  private const RESERVED_FIELDS = ['title', 'slug', 'parent', 'template', 'uuid', 'hidden'];

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

  private function groups(): ?array
  {
    if (!$this->sectionId) {
      return null;
    }

    $section = $this->view->blueprint()->section($this->sectionId);
    if (!$section || $section->type() !== 'modules') {
      return null;
    }

    return $section->templateGroups();
  }

  // No title field (modules are labelled by type or `label`); the anchor is a
  // relabeled slug field, shown unless create.anchor drives the anchor itself.
  public function coreFields(): array
  {
    $create = $this->blueprint()->create();
    $fields = [];

    if (!array_key_exists('anchor', $create)) {
      $field = Field::slug([
        'required' => false,
        'path'     => $this->parent instanceof Page ? '/' . $this->parent->id() . '/' : '/',
      ]);
      $field['label']  = t('modules.create.anchor');
      $field['icon']   = false;
      $field['before'] = '#';
      $field['help']   = false;
      if ($placeholder = ModuleRegistry::generateSlug($this->parentId, (string) $this->template)) {
        $field['placeholder'] = $placeholder;
      }
      $fields['slug'] = $field;
    }

    if (Uuids::enabled() === true) {
      $fields['uuid'] = Field::hidden();
    }

    return [
      ...$fields,
      'parent'   => Field::hidden(),
      'section'  => Field::hidden(),
      'template' => Field::hidden(),
      'view'     => Field::hidden(),
    ];
  }

  // Stores the type name as title (the card label comes from `label`) and
  // resolves the create.anchor template, exposing {{ module.* }}.
  public function resolveFieldTemplates(array $input): array
  {
    $create = $this->blueprint()->create();

    $input['title'] = (string) $this->blueprint()->title();

    if (array_key_exists('anchor', $create)) {
      $anchor = $create['anchor'];
      if (is_string($anchor)) {
        $page = $this->model()->clone(['content' => $input]);
        $input['slug'] = $page->toSafeString($anchor, ['module' => $page]);
      } else {
        $input['slug'] = '';
      }
    }

    // Normalize + uniquify (empty falls back to the type slug). Must match
    // createChild()'s Url::slug() so submit() can find the page to hide it.
    $slug = Str::slug((string) ($input['slug'] ?? ''));
    $input['slug'] = $slug !== ''
      ? (ModuleRegistry::uniqueSlug($this->parentId, $slug) ?? $slug)
      : (ModuleRegistry::generateSlug($this->parentId, (string) $this->template) ?? '');

    return $input;
  }

  // Skip unrenderable entries so the dialog still loads; guardListedFields()
  // rejects them at submit.
  public function customFields(): array
  {
    $blueprint = $this->blueprint();
    $fields    = $blueprint->fields();
    $custom    = [];

    foreach ($blueprint->create()['fields'] ?? [] as $name) {
      if (!$this->isRenderableField($name, $fields)) continue;
      $field = $fields[$name];
      $field['width'] = '1/1';
      $custom[$name] = $field;
    }

    return (new Form(fields: $custom, model: $this->model()))->fields()->toProps();
  }

  private function isRenderableField(string $name, array $fields): bool
  {
    $field = $fields[$name] ?? null;
    return $field
      && in_array($field['type'] ?? null, static::$fieldTypes, true)
      && !in_array($name, self::RESERVED_FIELDS, true);
  }

  public function load(): array
  {
    if (!$this->hasModuleBlueprints()) {
      throw new NotFoundException(t('modules.create.error.notemplates'));
    }

    $blueprints = $this->blueprints();
    $this->template ??= $blueprints[0]['name'] ?? null;

    // Reject a tampered template that isn't a registered module type.
    if (!ModuleRegistry::hasBlueprint($this->template)) {
      throw new NotFoundException(t('modules.create.error.notemplates'));
    }

    $fields  = $this->fields();
    $visible = array_filter($fields, fn($field) => ($field['hidden'] ?? null) !== true);

    if ($visible === [] && count($blueprints) < 2) {
      $response = $this->submit($this->value());
      Panel::go($response['redirect'] ?? $this->view->panel()->url(true));
    }

    $status = ModuleSectionRoutes::shouldAutopublish($this->blueprint(), $this->parent instanceof Page ? $this->parent : null)
      && !$this->bornInvalid()
      ? t('modules.visible')
      : t('modules.hidden');

    return [
      'component' => 'k-module-create-dialog',
      'props' => [
        'blueprints'   => $blueprints,
        'fields'       => $fields,
        'groups'       => $this->groups(),
        'submitButton' => tt('page.create', ['status' => $status]),
        'template'     => $this->template,
        'value'        => $this->value(),
      ],
    ];
  }

  public function submit(array|null $input = null): array
  {
    if ($this->parent instanceof Page && $this->parent->isModuleContainer()) {
      HostLock::ensureUnlocked($this->parent->parentModel());
    }

    // Guard at submit, not load, so a misconfigured type doesn't block its
    // siblings. create.title is replaced by `label`.
    if (array_key_exists('title', $this->blueprint()->create())) {
      throw new InvalidArgumentException(t('modules.create.error.title'));
    }
    $this->guardListedFields();

    $input ??= kirby()->request()->body()->toArray();

    $finalSlug = $this->sanitize($input)['slug'];
    $input['slug'] = $finalSlug;

    $response = parent::submit($input);

    if ($page = $this->parent->drafts()->find($finalSlug)) {
      $page = ModuleSectionRoutes::applyAutopublish($page);
      ModuleSectionRoutes::promote($page);
    }

    // core emits page.create, which sibling sections do not listen to
    $response['event'] = ['page.create', 'model.update'];

    return $response;
  }

  private function bornInvalid(): bool
  {
    $creatable = array_flip($this->blueprint()->create()['fields'] ?? []);
    $fields = array_diff_key($this->blueprint()->fields(), $creatable);
    $form = new Form(fields: $fields, model: $this->model());
    $form->fill($form->defaults());
    return $form->errors() !== [];
  }

  // A listed field the dialog can't render would silently vanish; name it instead.
  private function guardListedFields(): void
  {
    $fields  = $this->blueprint()->fields();
    $invalid = [];

    foreach ($this->blueprint()->create()['fields'] ?? [] as $name) {
      if (!$this->isRenderableField($name, $fields)) {
        $invalid[] = $name;
      }
    }

    if ($invalid !== []) {
      throw new InvalidArgumentException(
        tt('modules.create.error.fields', ['fields' => implode(', ', $invalid)])
      );
    }
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
