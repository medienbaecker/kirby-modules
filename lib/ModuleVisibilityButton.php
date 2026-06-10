<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Panel\Ui\Buttons\ViewButton;
use Kirby\Toolkit\I18n;

class ModuleVisibilityButton extends ViewButton
{
  public function __construct(Page $page)
  {
    $hidden = $page->isHidden();
    $disabled = $page->permissions()->cannot('update');

    $text = I18n::translate($hidden ? 'modules.hidden' : 'modules.visible');
    $title = I18n::translate('modules.visibility') . ': ' . $text;
    if ($disabled) {
      $title .= ' (' . I18n::translate('disabled') . ')';
    }

    parent::__construct(
      dialog: $page->panel()->url(true) . '/visibility',
      disabled: $disabled,
      icon: $hidden ? 'hidden' : 'preview',
      text: $text,
      theme: $hidden ? 'negative-icon' : 'positive-icon',
      title: $title,
    );
  }
}
