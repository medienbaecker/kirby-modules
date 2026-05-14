<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Content\Field;
use Kirby\Content\VersionId;
use Kirby\Http\Uri;
use Medienbaecker\Modules\ModuleRegistry;

class ModulePage extends Page
{
  public function previewUrl(VersionId|string $versionId = 'latest'): string|null
  {
    $language = kirby()->defaultLanguage()?->code();
    if (!$this->content($language)->hidden()->toBool()) {
      return parent::previewUrl($versionId);
    }

    if ($this->permissions()->can('preview') !== true) {
      return null;
    }

    $isChanges = $versionId instanceof VersionId
      ? $versionId->is('changes')
      : $versionId === 'changes';

    $query = [
      '_token'  => $this->version($versionId)->previewToken(),
      '_module' => $this->slug(),
    ];
    if ($isChanges) {
      $query['_version'] = 'changes';
    }

    return (new Uri($this->page()->url(), [
      'query'    => $query,
      'fragment' => $this->slug(),
    ]))->toString();
  }

  public function render(
    array $data = [],
    $contentType = 'html',
    VersionId|string|null $versionId = null
  ): string {
    $parentUrl = $this->page()->url();
    $query = [];

    $language = kirby()->defaultLanguage()?->code();
    $hidden = $this->content($language)->hidden()->toBool();
    $token = (string) get('_token');
    $version = (string) get('_version');

    if ($hidden) {
      $query['_module'] = $this->slug();
      if ($token !== '') $query['_token'] = $token;
    }

    if ($version === 'changes' && $token !== '') {
      $query['_module'] = $this->slug();
      $query['_token']  = $token;
      $query['_version'] = $version;
    }

    if (!empty($query)) {
      $parentUrl .= '?' . http_build_query($query);
    }

    go($parentUrl . '#' . $this->slug());
  }

  public function toHtml(array $params = []): string
  {
    $name = str_replace('module.', '', $this->intendedTemplate()->name());

    // Force the changes version during a verified preview so authors see
    // pending edits before publish.
    $previousRender = VersionId::$render;
    if ($this->isChangesPreviewRequest()) {
      VersionId::$render = VersionId::changes();
    }

    try {
      return snippet('modules/' . $name, [
        'page' => $this->parents()->first() ?? $this->site(),
        'module' => $this,
        ...$params
      ], true);
    } finally {
      VersionId::$render = $previousRender;
    }
  }

  private function isChangesPreviewRequest(): bool
  {
    if (get('_module') !== $this->slug()) return false;
    if (get('_version') !== 'changes') return false;
    $token = (string) get('_token');
    if ($token === '') return false;
    $expected = $this->version('changes')->previewToken();
    return hash_equals($expected, $token);
  }

  public function renderModule(array $params = []): void
  {
    echo $this->toHtml($params);
  }

  public function page(): Page|Site
  {
    return $this->parent()->parentModel();
  }

  public function hasTemplate(): bool
  {
    return ModuleRegistry::hasBlueprint($this->intendedTemplate()->name());
  }

  public function title(): Field
  {
    return new Field($this, 'title', $this->moduleName());
  }

  public function moduleName(): string
  {
    if (!$this->hasTemplate()) {
      return t('modules.missingTemplate');
    }
    return (string) $this->blueprint()->title();
  }

  public function moduleId(): string
  {
    return str_replace('.', '--', $this->intendedTemplate()->name());
  }

  public function parents(): Pages
  {
    $parents = parent::parents();
    return $parents->filter('intendedTemplate', '!=', 'modules');
  }

  public function metaDefaults(): array
  {
    return ['robotsIndex' => false];
  }
}
