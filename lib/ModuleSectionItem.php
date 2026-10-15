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
      'icon'              => $blueprint ? $blueprint->icon() : 'alert',
      'hidden'            => $child->isHidden(),
      'hasFields'         => $blueprint && !empty($blueprint->fields()),
      'hasPendingChanges' => $child->hasPendingChangesDeep(),
      'tabs'              => $blueprint ? $blueprint->tabs()->toButtonsProps() : [],
      'link'              => $child->panel()->url(),
      'permissions'       => self::permissions($child, $hasTemplate),
      'isLocked'          => $child->lock()?->isLocked() ?? false,
      'previewUrl'        => self::previewUrl($child),
    ];
  }

  private static function permissions(Page $child, bool $hasTemplate): array
  {
    $options = $child->panel()->options(['preview']);
    $options['move'] = ModuleSectionRoutes::canMove($child);

    // Kirby disables changeTemplate without a blueprint, but changing
    // the type is the way to recover such a module
    if ($hasTemplate === false) {
      $options['changeTemplate'] = $options['update'] === true
        && !empty(ModulesField::forModule($child)?->blueprints());
    }

    return $options;
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
