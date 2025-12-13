<?php

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Content\VersionId;

class ModulePage extends Page
{
  public function render(
    array $data = [],
    $contentType = 'html',
    VersionId|string|null $versionId = null
  ): string {
    $parentUrl = $this->page()->url();
    if ($token = get('_token')) {
      $parentUrl .= '?_token=' . $token;
    }

    go($parentUrl . '#' . $this->slug());
  }

  public function toHtml(array $params = []): string
  {
    $name = str_replace('module.', '', $this->intendedTemplate()->name());
    return snippet('modules/' . $name, [
      'page' => $this->parents()->first() ?? $this->site(),
      'module' => $this,
      ...$params
    ], true);
  }

  public function renderModule(array $params = [])
  {
    echo $this->toHtml($params);
  }
  public function page(): Page|Site
  {
    return $this->parent()->parent() ?? $this->site();
  }
  public function parentUrl(): string
  {
    return $this->page()->url();
  }
  public function moduleName(): string
  {
    return $this->blueprint()->title();
  }
  public function moduleId(): string
  {
    return str_replace('.', '--', $this->intendedTemplate());
  }
  public function parents(): Pages
  {
    $parents = parent::parents();
    return $parents->filter('slug', '!=', 'modules');
  }
  public function metaDefaults(): array
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
