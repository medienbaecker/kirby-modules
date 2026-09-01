<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Find;
use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;

class ModuleMoveDialog extends ModuleDialog
{
  public function load(): array
  {
    return [
      'component' => 'k-module-move-dialog',
      'props' => [
        'value' => [
          'move'   => $this->module->panel()->url(true),
          'parent' => $this->container()->uuid()?->toString() ?? $this->container()->id(),
        ]
      ]
    ];
  }

  public function submit(): array
  {
    $id     = kirby()->request()->get('parent');
    $target = empty($id) || $id === '/' || $id === 'site://' ? null : Find::page($id);

    if (!$target instanceof Page || !$target->isModuleContainer()) {
      throw new NotFoundException('Module not found');
    }

    ModuleSectionRoutes::moveTo($this->module, $target);

    return ['event' => 'modules.moved'];
  }

  private function container(): Page
  {
    return $this->module->parent();
  }
}
