<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Content\VersionId;

class ModulesPage extends Page
{
  public function render(
    array $data = [],
    $contentType = 'html',
    VersionId|string|null $versionId = null
  ): never {
    go($this->parentModel()->url());
  }

  public function metaDefaults(): array
  {
    return ['robotsIndex' => false];
  }
}
