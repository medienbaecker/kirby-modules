<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Panel\Collector\PagesCollector;
use Kirby\Panel\Controller\Dialog\PagePickerDialogController;

/**
 * Browses pages and their modules, skipping the module
 * containers. Only modules can be picked.
 */
class ModulePickerDialogController extends PagePickerDialogController
{
  /**
   * Visible modules of all modules fields, then the
   * subpages. The module that owns the field is left out.
   */
  public function children(Page|Site $parent): Pages
  {
    $children = new Pages([]);

    foreach (ModulesField::names($parent) as $name) {
      $children->add($parent->modules($name)->not($this->model));
    }

    $children->add($parent->children()->filter(
      fn (Page $child) => $child->isModuleContainer() === false
    ));

    return $children;
  }

  public function collector(): PagesCollector
  {
    return $this->collector ??= new class (
      $this,
      limit:  $this->limit,
      page:   $this->page,
      parent: $this->parent(),
      search: $this->search,
    ) extends PagesCollector {
      public function __construct(
        protected ModulePickerDialogController $picker,
        ...$props
      ) {
        parent::__construct(...$props);
      }

      protected function collect(): Pages
      {
        return $this->picker->children($this->parent());
      }
    };
  }

  /**
   * @param Page $model
   */
  public function item(ModelWithContent $model): array
  {
    return [
      ...parent::item($model),
      'hasChildren' => $this->children($model)->filter('isListable', true)->isNotEmpty(),
      'selectable'  => $model->isModule()
    ];
  }

  protected function parentProps(): array|null
  {
    $props  = parent::parentProps();
    $parent = $this->parent();

    if ($props['id'] !== null && $parent->isModule()) {
      $props['parent'] = $parent->page()->id();
    }

    return $props;
  }
}
