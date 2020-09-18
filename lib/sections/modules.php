<?php

use Kirby\Cms\Section;

$base = Section::$types['pages'];

return array_replace_recursive($base, [
	'props' => [
		'create' => moduleRegistry()['blueprints'],
		'info' => function(string $info = '{{ page.moduleName }}') {
			return $info;
		},
		'image' => false,
		'parent' => function($parent = null) {
			if($parent != null) {
				return $parent;
			}
			if($this->model()->find('modules')) {
				return 'page.find("modules")';
			}
			return null;
		}
	]
]);