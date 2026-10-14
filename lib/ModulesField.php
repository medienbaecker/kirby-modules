<?php

namespace Medienbaecker\Modules;

use Kirby\Api\Api;
use Kirby\Blueprint\Blueprint;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Form\Field\DisplayField;
use Kirby\Form\Fields;
use Kirby\Form\Mixin;
use Kirby\Reflection\Attributes\Prop;
use Kirby\Toolkit\A;
use Kirby\Toolkit\I18n;
use Throwable;

/**
 * Lists the modules of a page or the site. The modules are
 * the children of a hidden container page, which shares
 * the field's name as its slug.
 */
class ModulesField extends DisplayField
{
  use Mixin\EmptyState;
  use Mixin\Max;

  /**
   * Whether new modules are visible right away. Overrides
   * the global option and is overridden by the module type.
   */
  #[Prop(export: false)]
  protected bool|null $autopublish = null;

  /**
   * Layout of the module cards
   */
  #[Prop]
  protected string|null $layout = null;

  /**
   * Sets the minimum number of required modules
   */
  #[Prop]
  protected int|null $min = null;

  /**
   * Module types that can be added, incl. groups
   */
  #[Prop(computed: true)]
  protected array|string|null $templates = null;

  /**
   * Module types that cannot be added
   */
  #[Prop(export: false)]
  protected array|string $templatesIgnore = [];

  protected array|null $parsedTemplates = null;

  /**
   * Whether new modules can be added
   */
  public function add(): bool
  {
    return $this->isFull() === false;
  }

  /**
   * The modules are not part of the form values, so the field
   * loads and changes them through its own endpoints. Inside
   * the actions, `$this` is the API instance.
   */
  public function api(): array
  {
    $field = $this;

    return [
      [
        'pattern' => '',
        'method'  => 'GET',
        'action'  => fn (): array => $field->state(),
      ],
      [
        'pattern' => 'create-container',
        'method'  => 'POST',
        'action'  => function () use ($field): array {
          $field->createContainer();
          return ['status' => 'ok'];
        },
      ],
      [
        'pattern' => 'deleteAll',
        'method'  => 'POST',
        'action'  => function () use ($field): array {
          ModuleSectionRoutes::deleteAll($field->container());
          return ['status' => 'ok'];
        },
      ],
      [
        'pattern' => 'duplicate/(:any)',
        'method'  => 'POST',
        'action'  => fn (string $childId): array => ModuleSectionRoutes::duplicate(
          container: $field->container(),
          childId: $childId
        ),
      ],
      [
        'pattern' => 'fields',
        'method'  => 'POST',
        'action'  => function () use ($field): array {
          /** @var Api $this */
          return ModuleSectionRoutes::loadFieldsBatch(
            container: $field->container(),
            ids: $this->requestBody('ids')
          );
        },
      ],
      [
        'pattern' => 'move',
        'method'  => 'POST',
        'action'  => function () use ($field): array {
          /** @var Api $this */
          return ModuleSectionRoutes::move(
            container: $field->container(),
            childId: $this->requestBody('id'),
            ids: $this->requestBody('ids')
          );
        },
      ],
      [
        'pattern' => 'sort',
        'method'  => 'POST',
        'action'  => function () use ($field): array {
          /** @var Api $this */
          ModuleSectionRoutes::sort(
            container: $field->container(),
            ids: $this->requestBody('ids')
          );
          return ['status' => 'ok'];
        },
      ],
      [
        'pattern' => 'toggle-visibility/(:any)',
        'method'  => 'POST',
        'action'  => fn (string $childId): array => ModuleSectionRoutes::toggleVisibility(
          container: $field->container(),
          childId: $childId
        ),
      ],
    ];
  }

  public function autopublish(): bool|null
  {
    return $this->autopublish;
  }

  /**
   * Name and title of each module type that can be added
   */
  public function blueprints(): array
  {
    $blueprints = [];

    foreach ($this->templates() as $template) {
      try {
        $props = Blueprint::load('pages/' . $template);
        $blueprints[] = [
          'name'  => basename($props['name']),
          'title' => I18n::translate($props['title'], $props['title']),
        ];
      } catch (Throwable) {
        $blueprints[] = [
          'name'  => $template,
          'title' => ucfirst($template),
        ];
      }
    }

    return $blueprints;
  }

  /**
   * The hidden page that holds the modules as children
   */
  public function container(): Page|null
  {
    return $this->model()->find($this->name());
  }

  public function createContainer(): Page
  {
    return ModuleSectionRoutes::createContainer(
      model: $this->model(),
      slug: $this->name(),
      headline: $this->label()
    );
  }

  public function dialogs(): array
  {
    return [
      'create' => [
        'action' => fn () => new ModuleCreateDialog(field: $this),
      ],
    ];
  }

  public function empty(): string
  {
    return $this->stringTemplateI18n($this->empty) ?? I18n::translate('modules.empty');
  }

  /**
   * Errors are only reported for the publish gate.
   * The modules are not stored in the content of the model.
   */
  public function errors(): array
  {
    if ($this->isActive() === false) {
      return [];
    }

    $errors = [];

    if ($this->validateMax() === false) {
      $errors['max'] = I18n::template('error.field.pagelist.max.' . I18n::form($this->max), [
        'field' => $this->label(),
        'max'   => $this->max,
      ]);
    }

    if ($this->validateMin() === false) {
      $errors['min'] = I18n::template('error.field.pagelist.min.' . I18n::form($this->min), [
        'field' => $this->label(),
        'min'   => $this->min,
      ]);
    }

    return $errors;
  }

  /**
   * Finds the modules field with the given name
   * in the blueprint of a page or the site
   */
  public static function for(
    ModelWithContent $model,
    string $name
  ): static|null {
    $props = $model->blueprint()->field($name);

    if (($props['type'] ?? null) !== 'modules') {
      return null;
    }

    $field = (new Fields(
      fields: [$name => $props],
      model: $model
    ))->get($name);

    return $field instanceof static ? $field : null;
  }

  public function isFull(): bool
  {
    if ($this->max === null) {
      return false;
    }

    return $this->total() >= $this->max;
  }

  public function layout(): string|null
  {
    return $this->layout ?: null;
  }

  public function min(): int|null
  {
    return $this->min;
  }

  /**
   * The props of each module for the module cards
   */
  public function modules(): array
  {
    $modules = [];

    foreach ($this->container()?->children() ?? [] as $child) {
      $modules[] = ModuleSectionItem::for($child);
    }

    return $modules;
  }

  /**
   * Names of all modules fields in the blueprint of a page or the site
   */
  public static function names(ModelWithContent $model): array
  {
    $names = [];

    foreach ($model->blueprint()->fields() as $name => $props) {
      if (($props['type'] ?? null) === 'modules') {
        $names[] = $name;
      }
    }

    return $names;
  }

  /**
   * Parses the `templates` prop into the
   * flat list of types and the type groups
   */
  protected function parsedTemplates(): array
  {
    if ($this->parsedTemplates !== null) {
      return $this->parsedTemplates;
    }

    if (empty($this->templates) === true) {
      return $this->parsedTemplates = [
        'groups'    => [],
        'templates' => ModuleRegistry::moduleTemplates(),
      ];
    }

    return $this->parsedTemplates = ModuleRegistry::parseTemplates(A::wrap($this->templates));
  }

  /**
   * The current module data, which the
   * Panel loads through the field endpoint
   */
  public function state(): array
  {
    return [
      'add'     => $this->add(),
      'modules' => $this->modules(),
    ];
  }

  /**
   * Groups of module types for the create and change-type dialogs
   */
  public function templateGroups(): array|null
  {
    $templates = $this->templates();
    $groups    = [];

    foreach ($this->parsedTemplates()['groups'] as $key => $group) {
      $members = array_values(array_intersect($group['templates'], $templates));

      if ($members === []) {
        continue;
      }

      $groups[$key] = [
        'label'     => $group['label'],
        'open'      => $group['open'] !== false,
        'templates' => $members,
      ];
    }

    return $groups ?: null;
  }

  /**
   * Full names of all module types that can be added. Both short
   * (`text`) and full (`module.text`) names are accepted in
   * `templates`, `templatesIgnore` and `default`.
   */
  public function templates(): array
  {
    $templates = $this->parsedTemplates()['templates'];

    if ($ignore = $this->templatesIgnore()) {
      $ignore    = array_map(ModuleRegistry::qualify(...), $ignore);
      $templates = array_values(array_diff($templates, $ignore));
    }

    // `default` (from the core field mixin) names
    // the module type that is listed first
    if (is_string($this->default) === true) {
      $default = ModuleRegistry::qualify($this->default);

      if (in_array($default, $templates, true) === true) {
        $templates = array_values(array_unique([$default, ...$templates]));
      }
    }

    return $templates;
  }

  public function templatesIgnore(): array
  {
    return A::wrap($this->templatesIgnore);
  }

  public function total(): int
  {
    return $this->container()?->children()->count() ?? 0;
  }

  public function validateMax(): bool
  {
    return $this->max === null || $this->total() <= $this->max;
  }

  public function validateMin(): bool
  {
    return $this->min === null || $this->total() >= $this->min;
  }
}
