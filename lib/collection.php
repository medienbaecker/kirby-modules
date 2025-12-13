<?php

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

  public function toString(): string
  {
    return $this->toHtml();
  }
}
