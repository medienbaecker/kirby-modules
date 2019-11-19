<?php

use Kirby\Cms\Section;

// Get all blueprints that start with "module.":
$blueprints = [];
foreach (kirby()->blueprints() as $blueprint) {
	if(Str::startsWith($blueprint, 'module.')) $blueprints[] = $blueprint;
}

// Move default module to first position:
if($default = array_search(option('medienbaecker.modules.default', 'module.text'), $blueprints)) {
	$module_text = $blueprints[$default];
	unset($blueprints[$default]);
	array_unshift($blueprints, $module_text);
}

// Create a model for each of the blueprints
class ModulePage extends Page {
	public function url($options = null): string {
		return $this->parents()->filterBy("intendedTemplate", "!=", "modules")->first()->url() . '#' . $this->slug();
	}
}
$models = [];
foreach ($blueprints as $blueprint) {
	$models[$blueprint] = 'ModulePage';
}
$models['modules'] = 'ModulePage';

Kirby::plugin('medienbaecker/modules', [
	'options' => [
		'default' => 'module.text'
	],
	'sections' => [
		'modules' => array_replace_recursive(Section::$types['pages'], [
			'props' => [
				'create' => $blueprints,
				'info' => function(string $info = '{{ page.moduleName }}') {
					return $info;
				},
				'parent' => function() {
					if($this->model()->find("modules")) {
						return 'page.find("modules")';
					}
					else {
						return 'page';
					}
				}
			]
		])
	],
	'hooks' => [
		'page.create:after' => function ($page) {
			$modules = false;
			foreach($page->blueprint()->sections() as $section) {
				if($section->type() == "modules") $modules = true;
			}
			if($modules) {
				try {
					$modulesPage = $page->createChild([
						'content'  => ['title' => 'Modules'],
						'slug'     => 'modules',
						'template' => 'modules'
					]);
				}
				catch (Exception $error) {
					throw new Exception($error);
				}
				if($modulesPage) {
					$modulesPage->publish();
				}
			}
		}
	],
	'pageModels' => $models,
	'blueprints' => [
		'module/changeTemplate' => [
			'changeTemplate' => $blueprints
		]
	],
	'pageMethods' => [
		'isModule' => function () {
			return Str::startsWith($this->intendedTemplate(), 'module.');
		},
		'moduleName' => function () {
			return $this->blueprint()->title();
		}
	]
]);