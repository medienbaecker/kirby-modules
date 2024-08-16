<?php

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Template\Template;

class ModulePage extends Page {
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
    return str_replace('.', '--', $this->intendedTemplate());
  }
  public function parents(): Pages {
    $parents = parent::parents();
    return $parents->filter('slug', '!=', 'modules');
  }
	public function metaDefaults() {
		return ['robotsIndex' => false];
	}
}

class ModulesPage extends Page {
  public function url($options = null): string {
    return $this->parentUrl();
  }
  public function render(array $data = [], $contentType = 'html'): string {
    go($this->parentUrl());
  }
  public function metaDefaults() {
    return ['robotsIndex' => false];
  }
}
