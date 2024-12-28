<?php

use Kirby\Cms\Pages;
use Kirby\Template\Template;

class ModulesCollection extends Pages
{
  /**
   * Converts the object to a string
   *
   * @return string
   */
  public function toString(): string
  {
    $html = '';

    foreach ($this->data() as $module) {
      $html .= $module->renderModule();
    }

    return $html;
  }
}
