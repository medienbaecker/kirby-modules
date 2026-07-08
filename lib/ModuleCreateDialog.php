<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\App;
use Kirby\Cms\Find;
use Kirby\Cms\Page;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Form\Form;
use Kirby\Panel\Controller\Dialog\PageCreateDialogController;
use Kirby\Panel\Field;
use Kirby\Panel\Panel;
use Kirby\Panel\Ui\Dialog;
use Kirby\Panel\Ui\Dialog\FormDialog;
use Kirby\Toolkit\Str;
use Kirby\Uuid\Uuids;

class ModuleCreateDialog extends PageCreateDialogController
{
  // Field names the create dialog never renders (the page's routing fields;
  // `title` is the type name). Shared by customFields() and guardListedFields()
  // so the skip and the guard can't drift apart.
  private const RESERVED_FIELDS = ['title', 'slug', 'parent', 'template', 'uuid'];

  public static function factory(): static
  {
    $request = App::instance()->request();

    // `parent` is the modules container; the section lives on the view
    // (host) page's blueprint, so resolve it there, not on the container.
    $parent  = Find::parent($request->get('parent', $request->get('view', 'site')));
    $section = null;

    if ($name = $request->get('section')) {
      $view    = Find::parent($request->get('view', 'site'));
      $section = $view->blueprint()->section($name);
    }

    return new static(parent: $parent, section: $section);
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
        'path'     => $this->parent instanceof Page ? '/' . $this->parent->id() . '/' : '/',
      ]);
      $field['label']  = t('modules.create.anchor');
      $field['icon']   = false;
      $field['before'] = '#';
      $field['help']   = false;
      if ($this->parent instanceof Page && $placeholder = ModuleRegistry::generateSlug($this->parent->id(), $this->template())) {
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
  // resolves the create.anchor template, exposing {{ module.* }}. $fields
  // unused: title and slug follow our rules, not core's.
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
    // createChild()'s Url::slug() so submit() can find the page to hide it.
    $slug = Str::slug((string) ($input['slug'] ?? ''));
    $input['slug'] = $slug !== ''
      ? (ModuleRegistry::uniqueSlug($this->parent->id(), $slug) ?? $slug)
      : (ModuleRegistry::generateSlug($this->parent->id(), $this->template()) ?? '');

    return $input;
  }

  // Skip unrenderable entries so the dialog still loads; guardListedFields()
  // rejects them at submit (the K6 base throws on them at load instead).
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

  public function load(): Dialog
  {
    if (!$this->hasModuleBlueprints()) {
      throw new NotFoundException(t('modules.create.error.notemplates'));
    }

    $blueprints = $this->blueprints();

    // Reject a tampered template that isn't a registered module type.
    if (!ModuleRegistry::hasBlueprint($this->template())) {
      throw new NotFoundException(t('modules.create.error.notemplates'));
    }

    $fields  = $this->fields();
    $visible = array_filter($fields, fn($field) => ($field['hidden'] ?? null) !== true);

    if ($visible === [] && count($blueprints) < 2) {
      $response = $this->submit();
      Panel::go($response['redirect'] ?? Find::parent($this->request->get('view', 'site'))->panel()->url(true));
    }

    $status = ModuleSectionRoutes::shouldAutopublish($this->blueprint(), $this->parent instanceof Page ? $this->parent : null)
      ? t('modules.visible')
      : t('modules.hidden');

    return new FormDialog(
      component:    'k-module-create-dialog',
      blueprints:   $blueprints,
      fields:       $fields,
      submitButton: tt('page.create', ['status' => $status]),
      template:     $this->template(),
      value:        $this->value(),
    );
  }

  public function submit(): array
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
    $this->guardRequiredFields();

    // parent::submit() doesn't return the created page, so pre-compute its final
    // slug to find it afterwards and apply the hidden flag. sanitize() is
    // deterministic until the page exists, so parent's run computes the same slug.
    $finalSlug = $this->sanitize($this->request->get())['slug'];

    $response = parent::submit();

    if ($page = $this->parent->find($finalSlug)) {
      ModuleSectionRoutes::applyAutopublish($page);
    }

    return $response;
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

  // Forced status:listed makes core validate all fields; a required field the
  // dialog can't show would fail cryptically. Name the offenders up front.
  private function guardRequiredFields(): void
  {
    $blueprint = $this->blueprint();
    $included  = $blueprint->create()['fields'] ?? [];
    $missing   = [];

    foreach ($blueprint->fields() as $name => $field) {
      if (($field['required'] ?? false) !== true || $name === 'title') {
        continue;
      }
      // A non-empty default satisfies the required check on its own.
      if (($field['default'] ?? '') !== '') {
        continue;
      }
      $supported = in_array($field['type'] ?? null, static::$fieldTypes, true);
      if (!$supported || !in_array($name, $included, true)) {
        $missing[] = $name;
      }
    }

    if ($missing !== []) {
      throw new InvalidArgumentException(
        tt('modules.create.error.required', ['fields' => implode(', ', $missing)])
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
