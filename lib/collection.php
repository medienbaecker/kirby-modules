<?php

use Kirby\Cms\Pages;
use Kirby\Template\Template;

class ModulesCollection extends Pages {
  /**
   * Converts the object to a string
   *
   * @return string
   */
  public function toString(): string {
    $html = '';

    foreach ($this->data() as $module) {
      $moduleTemplate = new Template($module->intendedTemplate());
      $html .= $moduleTemplate->render([
        'page' => $module->parents()->first(),
        'module' => $module,
        'site' => site(),
      ]);
    }

    return $html;
  }
}
