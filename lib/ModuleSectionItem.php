<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\Page;
use Kirby\Toolkit\I18n;

class ModuleSectionItem
{
  public static function for(Page $child): array
  {
    $templateName = $child->intendedTemplate()->name();
    $hasTemplate = ModuleRegistry::hasBlueprint($templateName);
    $blueprint = $hasTemplate ? $child->blueprint() : null;

    return [
      'id'                => $child->id(),
      'slug'              => $child->slug(),
      'template'          => $templateName,
      'hasTemplate'       => $hasTemplate,
      // Card label = the module title (live `label` or type name); else "missing".
      'moduleName'        => $blueprint ? (string) $child->title() : I18n::translate('modules.missingTemplate'),
      'icon'              => $blueprint ? ($blueprint->icon() ?? 'box') : 'alert',
      'hidden'            => $child->isHidden(),
      'hasFields'         => $blueprint && !empty($blueprint->fields()),
      'hasPendingChanges' => $child->version('changes')->exists('*'),
      'tabs'              => $blueprint ? $blueprint->tabs() : [],
      'link'              => $child->panel()->url(),
      'permissions'       => $child->panel()->options(['preview']),
      // The host page lock covers the UI; this only guards adoption
      // in reconcileState() while no mirror exists yet.
      'isLocked'          => $child->lock()?->isLocked() ?? false,
      'previewUrl'        => self::previewUrl($child),
    ];
  }

  private static function previewUrl(Page $child): ?string
  {
    if ($child->isHidden()) {
      return $child->previewUrl();
    }
    $host = $child->parent()?->parentModel();
    return $host ? $host->url() . '#' . $child->slug() : null;
  }
}
