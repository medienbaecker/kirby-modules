<?php

namespace Medienbaecker\Modules;

use Kirby\Http\Request;
use Kirby\Panel\Controller\Dialog\PageDeleteDialogController;
use Kirby\Panel\Ui\Dialog;
use Kirby\Panel\Ui\Dialog\RemoveDialog;

// Modules live in container subpages, so Kirby would ask to type the title
// for every page with a modules field.
class PageDeleteDialog extends PageDeleteDialogController
{
  public function load(): Dialog
  {
    if ($this->hasOtherSubpages()) {
      return parent::load();
    }

    return new RemoveDialog(
      text: $this->i18nHtml($this->page->isModule() ? 'modules.delete.confirm' : 'page.delete.confirm', [
        'title' => $this->page->title()->value()
      ])
    );
  }

  public function submit(): array
  {
    if (!$this->hasOtherSubpages()) {
      $this->request = new Request([
        'body' => ['check' => $this->page->title()->value()]
      ]);
    }

    return parent::submit();
  }

  protected function hasOtherSubpages(): bool
  {
    return $this->page->childrenAndDrafts()
      ->filter(fn ($child) => !$child->isModuleContainer())
      ->count() > 0;
  }
}
