<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Content\VersionId;

class ModulePage extends Page
{
  /**
   * Redirect to parent page with preview params instead of rendering
   */
  public function render(
    array $data = [],
    $contentType = 'html',
    VersionId|string|null $versionId = null
  ): string {
    $parentUrl = $this->page()->url();

    if ($this->isDraft()) {
      $parentUrl .= '?_module=' . $this->slug();
      if ($token = get('_token')) {
        $parentUrl .= '&_token=' . $token;
      }
    }

    go($parentUrl . '#' . $this->slug());
  }

  /**
   * Render the module's snippet to HTML
   */
  public function toHtml(array $params = []): string
  {
    $name = str_replace('module.', '', $this->intendedTemplate()->name());
    return snippet('modules/' . $name, [
      'page' => $this->parents()->first() ?? $this->site(),
      'module' => $this,
      ...$params
    ], true);
  }

  public function renderModule(array $params = []): void
  {
    echo $this->toHtml($params);
  }

  /**
   * The actual parent page (skips the modules container)
   * and falls back to site for global modules
   */
  public function page(): Page|Site
  {
    return $this->parent()->parent() ?? $this->site();
  }

  /**
   * Display name from the blueprint title
   */
  public function moduleName(): string
  {
    return $this->blueprint()->title();
  }

  /**
   * CSS-safe BEM identifier from template name (e.g. module--text)
   */
  public function moduleId(): string
  {
    return str_replace('.', '--', $this->intendedTemplate());
  }

  /**
   * Ancestor chain without the modules container
   */
  public function parents(): Pages
  {
    $parents = parent::parents();
    return $parents->filter('intendedTemplate', '!=', 'modules');
  }

  /**
   * Prevent search engine indexing
   */
  public function metaDefaults(): array
  {
    return ['robotsIndex' => false];
  }
}
