<?php

use Kirby\Cms\Pages;
use Kirby\Cms\Template;

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
      $page = $this->getParentPage($module);
      $html .= $moduleTemplate->render([
        'page' => $page,
        'module' => $module,
        'site' => site(),
      ]);
    }

    return $html;
  }

  private function getParentPage($module): ?Page
  {
    $page = $module->parent();
    while (!is_null($page) and get_class($page) === "ModulesPage") {
      $page = $page->parent();
    }

    return $page;
  }
}
