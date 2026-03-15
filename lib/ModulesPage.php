<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Content\VersionId;

class ModulesPage extends Page
{
  /**
   * Redirect to parent page
   */
  public function render(
    array $data = [],
    $contentType = 'html',
    VersionId|string|null $versionId = null
  ): string {
    go($this->parent()->url());
  }

  /**
   * Prevent search engine indexing
   */
  public function metaDefaults(): array
  {
    return ['robotsIndex' => false];
  }
}
