<?php

namespace Medienbaecker\Modules;

use Kirby\Form\Field\PagePickerField;
use Kirby\Reflection\Attributes\Prop;
use Kirby\Toolkit\I18n;

/**
 * Picks a module of any page or the site. Stores the
 * module's UUID like a `pagepicker` field.
 */
class ModulePickerField extends PagePickerField
{
  /**
   * Whether more than one module can be picked
   */
  #[Prop]
  protected bool $multiple = false;

  public function dialogs(): array
  {
    return [
      'picker' => fn () => new ModulePickerDialogController(...[
        'model'     => $this->model(),
        'hasSearch' => $this->search(),
        'image'     => $this->image(),
        'info'      => $this->info(),
        'max'       => $this->max(),
        'multiple'  => $this->multiple(),
        'text'      => $this->text(),
        ...$this->picker()
      ])
    ];
  }

  public function empty(): string|null
  {
    return parent::empty() ?? I18n::translate(
      $this->multiple() ? 'modules.picker.empty' : 'modules.picker.empty.single'
    );
  }
}
