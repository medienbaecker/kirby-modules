<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Pages;

class ModulesCollection extends Pages
{
  /**
   * Render all modules to concatenated HTML
   */
  public function toHtml(): string
  {
    $html = '';
    foreach ($this->data as $module) {
      $html .= $module->toHtml();
    }
    return $html;
  }

  /**
   * Alias for toHtml()
   * enables echo $page->modules()
   */
  public function toString(): string
  {
    return $this->toHtml();
  }
}
