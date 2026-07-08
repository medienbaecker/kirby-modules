<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;
use Kirby\Panel\Controller\DialogController;

// Base for dialogs that operate on a single module. The module id arrives
// as a route argument (e.g. `modules/change-slug/(:any)`) and is resolved
// once here. The generic 404 is deliberate: it doesn't reveal whether an
// id exists but isn't a module.
abstract class ModuleDialog extends DialogController
{
  protected Page $module;

  public function __construct(string $id)
  {
    parent::__construct();
    $module = $this->kirby->page(str_replace('+', '/', $id));
    if (!$module || !$module->isModule()) {
      throw new NotFoundException('Module not found');
    }
    $this->module = $module;
  }

  abstract public function load(): array;

  // No abstract submit(): Controller::submit() is concrete, PHP forbids re-abstracting.
}
