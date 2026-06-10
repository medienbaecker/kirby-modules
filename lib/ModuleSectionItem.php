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

    // Virtual modules (no content folder) are read-only: edits wouldn't
    // persist, so they get no Panel link and no write permissions.
    $isVirtual = $child->exists() === false;

    return [
      'id'                => $child->id(),
      'slug'              => $child->slug(),
      'template'          => $templateName,
      'hasTemplate'       => $hasTemplate,
      'isVirtual'         => $isVirtual,
      'moduleName'        => $blueprint ? (string) $blueprint->title() : I18n::translate('modules.missingTemplate'),
      'icon'              => $blueprint ? ($blueprint->icon() ?? 'box') : 'alert',
      'hidden'            => $child->isHidden(),
      'hasFields'         => $blueprint && !empty($blueprint->fields()),
      'hasPendingChanges' => $child->version('changes')->exists('*'),
      'tabs'              => $blueprint ? $blueprint->tabs() : [],
      'link'              => $isVirtual ? null : $child->panel()->url(),
      'permissions'       => $isVirtual ? ['preview' => true] : $child->panel()->options(['preview']),
      'lock'              => self::lock($child),
      'previewUrl'        => self::previewUrl($child),
    ];
  }

  public static function lock(Page $child): ?array
  {
    $lock = $child->lock();
    $data = $lock?->toArray();
    if (!$data || !$data['isLocked']) {
      return $data;
    }

    $user = $lock->user();
    $data['user']['name'] = $user?->name()->isNotEmpty()
      ? $user->name()->value()
      : $user?->email();
    return $data;
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
