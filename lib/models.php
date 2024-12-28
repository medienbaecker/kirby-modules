<?php

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Content\VersionId;
use Kirby\Template\Template;

class ModulePage extends Page
{
  public function parentUrl(): string
  {
    return $this->parents()->count() ? $this->parents()->first()->url() : $this->site()->url();
  }
  public function render(
    array $data = [],
    $contentType = 'html',
    VersionId|string|null $versionId = null
  ): string {
    go($this->parent()->url() . '#' . $this->slug());
  }
  public function renderModule(array $params = [])
  {
    $controllerData = $this->controller();
    $moduleTemplate = new Template($this->intendedTemplate());
    echo $moduleTemplate->render(array_merge($controllerData, [
      'page' => $this->parents()->first() ?? $this->site(),
      'module' => $this,
      'site' => $this->site(),
      ...$params
    ]));
  }
  public function moduleName()
  {
    return $this->blueprint()->title();
  }
  public function moduleId()
  {
    return str_replace('.', '--', $this->intendedTemplate());
  }
  public function parents(): Pages
  {
    $parents = parent::parents();
    return $parents->filter('slug', '!=', 'modules');
  }
  public function metaDefaults()
  {
    return ['robotsIndex' => false];
  }
}

class ModulesPage extends Page
{
  public function render(
    array $data = [],
    $contentType = 'html',
    VersionId|string|null $versionId = null
  ): string {
    go($this->parent()->url());
  }
  public function metaDefaults()
  {
    return ['robotsIndex' => false];
  }
}
