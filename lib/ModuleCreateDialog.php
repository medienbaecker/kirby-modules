<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Form\Form;
use Kirby\Panel\Controller\Dialog\PageCreateDialogController;
use Kirby\Panel\Field;
use Kirby\Panel\Panel;
use Kirby\Panel\Ui\Dialog\FormDialog;
use Kirby\Toolkit\Str;
use Kirby\Uuid\Uuids;

class ModuleCreateDialog extends PageCreateDialogController
{
  // Field names the create dialog never renders (the page's routing fields;
  // `title` is the type name). Shared by customFields() and guardListedFields()
  // so the skip and the guard can't drift apart.
  private const RESERVED_FIELDS = ['title', 'slug', 'parent', 'template', 'uuid', 'hidden'];

  // The dialog is opened through the modules field's own dialog
  // route, which creates the container on demand.
  public function __construct(
    protected ModulesField $field
  ) {
    parent::__construct(
      parent: $field->container() ?? $field->createContainer(),
      blueprints: $field->blueprints()
    );
  }

  public function blueprints(): array
  {
    return array_map(
      fn(array $blueprint) => [...$blueprint, ...ModuleRegistry::typeVisuals($blueprint['name'])],
      parent::blueprints()
    );
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
        'path'     => '/' . $this->parent->id() . '/',
      ]);
      $field['label']  = $this->i18n('modules.create.anchor');
      $field['icon']   = false;
      $field['before'] = '#';
      $field['help']   = false;
      if ($placeholder = ModuleRegistry::generateSlug($this->parent->id(), $this->template())) {
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
      'template' => Field::hidden(),
    ];
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

    return (new Form(fields: $custom, model: $this->model()))->fields()->toProps(defaults: true);
  }

  private function isRenderableField(string $name, array $fields): bool
  {
    $field = $fields[$name] ?? null;
    return $field
      && in_array($field['type'] ?? null, static::$fieldTypes, true)
      && !in_array($name, self::RESERVED_FIELDS, true);
  }

  public function load(): FormDialog
  {
    $blueprints = $this->blueprints();
    $fields     = $this->fields();
    $visible = array_filter($fields, fn($field) => ($field['hidden'] ?? null) !== true);

    if ($visible === [] && count($blueprints) < 2) {
      $this->submit();
      Panel::go($this->field->model()->panel()->url(true));
    }

    $status = ModuleSectionRoutes::shouldAutopublish($this->blueprint(), $this->parent)
      && !$this->bornInvalid()
      ? $this->i18n('modules.visible')
      : $this->i18n('modules.hidden');

    return new FormDialog(
      component:    'k-module-create-dialog',
      blueprints:   $blueprints,
      fields:       $fields,
      groups:       $this->field->templateGroups(),
      size:         'large',
      submitButton: $this->i18n('page.create', ['status' => $status]),
      template:     $this->template(),
      value:        $this->value()
    );
  }

  // Stores the type name as title (the card label comes from `label`) and
  // resolves the create.anchor template, exposing {{ module.* }}.
  public function resolveFieldTemplates(array $input, array $fields): array
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
    // createChild()'s Url::slug() so the module is created under this slug.
    $slug = Str::slug((string) ($input['slug'] ?? ''));
    $input['slug'] = $slug !== ''
      ? (ModuleRegistry::uniqueSlug($this->parent->id(), $slug) ?? $slug)
      : (ModuleRegistry::generateSlug($this->parent->id(), $this->template()) ?? '');

    return $input;
  }

  public function submit(): array
  {
    HostLock::ensureUnlocked($this->field->model());

    // Guard at submit, not load, so a misconfigured type doesn't block its
    // siblings. create.title is replaced by `label`.
    if (array_key_exists('title', $this->blueprint()->create())) {
      throw new InvalidArgumentException($this->i18n('modules.create.error.title'));
    }
    $this->guardListedFields();

    // Modules are always created as drafts (see ModuleRegistry::add())
    // and promoted to listed once their visibility is set.
    $input = $this->sanitize($this->request->get());
    $this->validate($input);

    $page = $this->parent->createChild($input);
    $page = ModuleSectionRoutes::applyAutopublish($page);
    ModuleSectionRoutes::promote($page);

    // core emits page.create, which sibling fields do not listen to
    return ['event' => ['page.create', 'model.update']];
  }

  // Rejects a field without module types and a tampered
  // template that isn't one of the field's module types.
  public function template(): string
  {
    $names    = array_column($this->blueprints, 'name');
    $template = $this->request->get('template', $names[0] ?? null);

    if (!in_array($template, $names, true)) {
      throw new NotFoundException($this->i18n('modules.create.error.notemplates'));
    }

    return $template;
  }

  private function bornInvalid(): bool
  {
    $creatable = array_flip($this->blueprint()->create()['fields'] ?? []);
    $fields = array_diff_key($this->blueprint()->fields(), $creatable);
    $form = new Form(fields: $fields, model: $this->model());
    $form->fill(input: $form->defaults());
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
        $this->i18n('modules.create.error.fields', ['fields' => implode(', ', $invalid)])
      );
    }
  }
}
