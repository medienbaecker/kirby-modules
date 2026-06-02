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
    $language = kirby()->defaultLanguage()?->code();

    return [
      'id'                => $child->id(),
      'slug'              => $child->slug(),
      'template'          => $templateName,
      'hasTemplate'       => $hasTemplate,
      'moduleName'        => $blueprint ? (string) $blueprint->title() : I18n::translate('modules.missingTemplate'),
      'icon'              => $blueprint ? ($blueprint->icon() ?? 'box') : 'alert',
      'hidden'            => $child->content($language)->hidden()->toBool(),
      'hasFields'         => $blueprint && !empty($blueprint->fields()),
      'hasPendingChanges' => $child->version('changes')->exists('*'),
      'tabs'              => $blueprint ? $blueprint->tabs() : [],
      'link'              => $child->panel()->url(),
      'permissions'       => $child->panel()->options(['preview']),
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
    $language = kirby()->defaultLanguage()?->code();
    if ($child->content($language)->hidden()->toBool()) {
      return $child->previewUrl();
    }
    $host = $child->parent()?->parentModel();
    return $host ? $host->url() . '#' . $child->slug() : null;
  }
}
