<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Pages;

class ModulesCollection extends Pages
{
  public function toHtml(): string
  {
    $html = '';
    foreach ($this->data as $module) {
      $html .= $module->toHtml();
    }
    return $html;
  }

  // Lets `echo $page->modules()` work via Kirby's Collection __toString().
  public function toString(): string
  {
    return $this->toHtml();
  }
}
