<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Section;
use Kirby\Cms\Site;
use Kirby\Content\Field;
use Kirby\Content\VersionId;
use Kirby\Http\Uri;
use Kirby\Toolkit\Str;
use Medienbaecker\Modules\ModuleRegistry;

class ModulePage extends Page
{
  public function changeTemplate(string $template): static
  {
    return ModuleSectionRoutes::reconcileVisibility(parent::changeTemplate($template));
  }

  private bool $resolvingSection = false;

  // Kirby derives the change-template list, its permission and its validation
  // from this one method, so scoping it to the owning section is what keeps a
  // module from being changed to a type that section does not allow. Building
  // that section asks its own items for permissions, which lands back here.
  public function blueprints(string|null $inSection = null): array
  {
    if ($this->resolvingSection === true) {
      return parent::blueprints($inSection);
    }

    $this->resolvingSection = true;

    try {
      return $this->ownerSection()?->blueprints() ?? parent::blueprints($inSection);
    } finally {
      $this->resolvingSection = false;
    }
  }

  // A module's container slug equals its owning section's name on the host
  // page (see hooks.php). Fetch that one section by name rather than iterating
  // sections() — the latter also instantiates the host's other sections
  // (e.g. files), which can error outside a normal request.
  public function ownerSection(): ?Section
  {
    $container = $this->parent();
    $host = $container?->parentModel();

    if (!$host) {
      return null;
    }

    $section = $host->blueprint()->section($container->slug());

    return ($section && $section->type() === 'modules') ? $section : null;
  }

  private bool $hiddenWriteAllowed = false;

  public function allowHiddenWrite(): static
  {
    $this->hiddenWriteAllowed = true;
    return $this;
  }

  public function update(
    array|null $input = null,
    string|null $languageCode = null,
    bool $validate = false
  ): static {
    $allowed = $this->hiddenWriteAllowed;
    $this->hiddenWriteAllowed = false;
    if (!$allowed && is_array($input)) {
      // match Kirby's key normalization: " hidden", "hidden!" etc. all store as hidden
      $input = array_filter($input, fn($k) => Str::slug((string) $k) !== 'hidden', ARRAY_FILTER_USE_KEY);
    }
    return parent::update($input, $languageCode, $validate && !$this->isHidden());
  }

  public function previewUrl(VersionId|string $versionId = 'latest'): string|null
  {
    if (!$this->isHidden()) {
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
  ): never {
    $parentUrl = $this->page()->url();
    $query = [];

    $hidden = $this->isHidden();
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
    $render = fn(): string => snippet('modules/' . $name, [
      'page' => $this->parents()->first() ?? $this->site(),
      'module' => $this,
      ...$params
    ], true);

    // Force the changes version during a verified preview so authors see
    // pending edits before publish.
    if ($this->isChangesPreviewRequest()) {
      return VersionId::render(VersionId::changes(), $render);
    }

    return $render();
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

  private bool $resolvingTitle = false;

  public function title(): Field
  {
    // The flag stops a `label` of {{ module.title }} recursing back into title().
    if ($this->resolvingTitle === false && $this->hasTemplate()) {
      $label = $this->blueprint()->label();
      if (is_string($label)) {
        $this->resolvingTitle = true;
        try {
          $resolved = $this->toSafeString($label, ['module' => $this]);
        } finally {
          $this->resolvingTitle = false;
        }
        if ($resolved !== '') {
          return new Field($this, 'title', $resolved);
        }
      }
    }

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

  public function isMovableTo(Page|Site $parent): bool
  {
    return $parent instanceof Page
      && $parent->isModuleContainer()
      && parent::isMovableTo($parent);
  }

  public function metaDefaults(): array
  {
    return ['robotsIndex' => false];
  }
}
