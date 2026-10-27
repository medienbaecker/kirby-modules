<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Panel\Ui\Item\PageItem;
use Kirby\Toolkit\Str;

/**
 * Browses pages like the page picker. A page can be picked when one
 * of its modules fields takes the module; the field moves it there.
 */
class ModuleMoveDialog extends ModuleDialog
{
  public function load(): array
  {
    $parent = $this->parent();
    $host   = $this->module->parent()->parentModel();

    return [
      'component' => 'k-page-picker-dialog',
      'props' => [
        'empty'        => ['icon' => 'page', 'text' => $this->i18n('dialog.pages.empty')],
        'items'        => $this->items($parent),
        'multiple'     => false,
        'pagination'   => ['hide' => true],
        'parent'       => $this->parentProps($parent),
        'submitButton' => ['icon' => 'parent', 'text' => $this->i18n('move')],
        'value'        => Str::split($this->request->get('value', $this->selectionId($host))),
      ]
    ];
  }

  private function accepts(ModulesField $field): bool
  {
    return
      $field->container()?->is($this->module->parent()) !== true &&
      in_array($this->module->intendedTemplate()->name(), $field->templates()) &&
      ($field->max() === null || $field->total() < $field->max());
  }

  private function item(Page|Site $model): array
  {
    $fields = [];

    foreach (ModulesField::names($model) as $name) {
      $field = ModulesField::for($model, $name);
      if ($this->accepts($field)) {
        $fields[] = ['name' => $name, 'label' => $field->label()];
      }
    }

    $props = $model instanceof Site
      ? ['id' => 'site://', 'text' => $model->title()->value(), 'image' => ['icon' => 'home', 'back' => 'pattern', 'color' => 'gray-500']]
      : (new PageItem(page: $model))->props();

    return [
      ...$props,
      'api'         => $model->panel()->path(),
      'fields'      => $fields,
      'selectable'  => $fields !== [] || $this->module->parent()->parentModel()->is($model),
      'hasChildren' => $model instanceof Page && $this->pages($model)->isNotEmpty(),
    ];
  }

  private function items(Page|Site $parent): array
  {
    $models = $this->pages($parent)->values();

    if ($parent instanceof Site && ModulesField::names($parent) !== []) {
      array_unshift($models, $parent);
    }

    if ($search = $this->request->get('search')) {
      $models = array_filter(
        $models,
        fn (Page|Site $model) => Str::contains($model->title()->value(), $search, true)
      );
    }

    return array_values(array_map($this->item(...), $models));
  }

  /**
   * Modules with modules fields of their own, then the subpages
   */
  private function pages(Page|Site $parent): Pages
  {
    $pages = new Pages([]);

    foreach (ModulesField::names($parent) as $name) {
      foreach ($parent->find($name)?->children() ?? [] as $module) {
        if (
          $module->isNot($this->module) &&
          $module->isListable() &&
          ModulesField::names($module) !== []
        ) {
          $pages->add($module);
        }
      }
    }

    return $pages->add($parent->childrenAndDrafts()->filter(
      fn (Page $child) => $child->isModuleContainer() === false && $child->isListable()
    ));
  }

  private function parent(): Page|Site
  {
    $parent = kirby()->page($this->request->get('parent') ?? '');

    if (
      !$parent ||
      $parent->isAccessible() === false ||
      $parent->isModuleContainer() ||
      $parent->is($this->module) ||
      $parent->isDescendantOf($this->module)
    ) {
      return site();
    }

    return $parent;
  }

  private function parentProps(Page|Site $parent): array
  {
    if ($parent instanceof Site) {
      return ['id' => null, 'parent' => null, 'title' => $parent->title()->value()];
    }

    $up = $parent->isModule() ? $parent->page() : $parent->parentModel();

    return [
      'id'     => $parent->id(),
      'parent' => $up instanceof Site ? null : $up->id(),
      'title'  => $parent->title()->value(),
    ];
  }

  private function selectionId(Page|Site $model): string
  {
    return $model instanceof Site ? 'site://' : ($model->uuid()?->toString() ?? $model->id());
  }
}
