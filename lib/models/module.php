<?php

class ModulePage extends Page {
	public function url($options = null): string {
		return $this->parents()->filterBy('intendedTemplate', '!=', 'modules')->first()->url() . '#' . $this->slug();
	}
}