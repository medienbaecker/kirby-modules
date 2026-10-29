<?php

namespace Medienbaecker\Modules;

use Generator;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Exception\FormValidationException;
use Kirby\Form\Form;

class ModuleChangesCascade
{
  private static function descendants(Page|Site $model): Generator
  {
    foreach (ModulesField::names($model) as $name) {
      $container = $model->find($name);
      if (!$container) continue;

      foreach ($container->children() as $child) {
        yield $child;
        yield from static::descendants($child);
      }
    }
  }

  public static function validate(Page|Site $model): void
  {
    $invalid = [];
    $details = [];

    foreach (static::descendants($model) as $child) {
      if ($child->guards()->isAvailable('update') === false) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current') === false) continue;

      $form = Form::for($child, 'current');
      $form->submit(input: $changes->content('current')->toArray());
      if ($form->isInvalid()) {
        $title = (string) $child->title();
        $invalid[] = $title;
        foreach ($form->errors() as $name => $error) {
          $details[$child->id() . '/' . $name] = [
            'label'   => $title . ': ' . $error['label'],
            'message' => $error['message'],
          ];
        }
      }
    }

    if ($invalid !== []) {
      throw new FormValidationException(
        message: tt('modules.publish.invalid', ['modules' => implode(', ', $invalid)]),
        details: $details
      );
    }
  }

  public static function publish(Page|Site $model): void
  {
    foreach (static::descendants($model) as $child) {
      if ($child->guards()->isAvailable('update') === false) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current')) {
        $changes->publish(language: 'current');
      }
    }
  }

  public static function discard(Page|Site $model): void
  {
    foreach (static::descendants($model) as $child) {
      if ($child->guards()->isAvailable('update') === false) continue;
      $changes = $child->version('changes');
      if ($changes->exists('current')) {
        $changes->delete('current');
      }
    }
  }
}
