<?php

use Kirby\Cms\Section;

$base = Section::$types['pages'];

$blueprints = [];
foreach (moduleRegistry()['blueprints'] as $blueprint => $file) {
	if(Str::startsWith($blueprint, 'pages/module.')) {
		$blueprints[] = str_replace('pages/', '', $blueprint);
	}
}
$default = array_search(option('medienbaecker.modules.default', 'module.text'), $blueprints);
if($default !== false) {
	$module_text = $blueprints[$default];
	unset($blueprints[$default]);
	array_unshift($blueprints, $module_text);
}

return array_replace_recursive($base, [
	'props' => [
		'create' => $blueprints,
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