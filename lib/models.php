<?php

use Kirby\Cms\Template;

class ModulePage extends Page {
  public static function create(array $props) {
    if (option('medienbaecker.modules.autopublish', false)) {
      $props['num'] = 9999;
    }
    return parent::create($props);
  }
  public function parentUrl(): string {
    return $this->parents()->count() > 0 ? $this->parents()->first()->url() : $this->site()->url();
  }
  public function url($options = null): string {
    return $this->parentUrl() . '#' . $this->slug();
  }
  public function render(array $data = [], $contentType = 'html'): string {
    go($this->parentUrl() . '#' . $this->slug());
  }
  public function renderModule() {
    $moduleTemplate = new Template($this->intendedTemplate());
    echo $moduleTemplate->render([
      'page' => $this->parents()->first() ?? $this->site(),
      'module' => $this,
      'site' => $this->site()
    ]);
  }
  public function moduleName() {
    return $this->blueprint()->title();
  }
  public function moduleId() {
    return str_replace('.', '__', $this->intendedTemplate());
  }
  public function parents() {
    $parents = parent::parents();
    return $parents->filter('slug', '!=', 'modules');
  }
}

class ModulesPage extends Page {
  public function url($options = null): string {
    return $this->parentUrl();
  }
  public function render(array $data = [], $contentType = 'html'): string {
    go($this->parentUrl());
  }
}
