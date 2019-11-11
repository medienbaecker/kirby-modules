<?php

use Kirby\Cms\Section;

$blueprints = [];
foreach (kirby()->blueprints() as $blueprint) {
	if(Str::startsWith($blueprint, 'module.')) $blueprints[] = $blueprint;
}

Kirby::plugin('medienbaecker/modules', [
	'sections' => [
		'modules' => array_replace_recursive(Section::$types['pages'], [
			'props' => [
				'create' => $blueprints,
				'info' => function(string $info = '{{ page.moduleName }}') {
					return $info;
				},
				'parent' => function(string $parent = 'page.find("modules")') {
					return $parent;
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
						'content'  => ['title' => 'modules'],
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
	'blueprints' => [
		'pages/modules' => [
			'title' => 'Modules',
			'options' => [
				'changeSlug' => false,
				'changeStatus' => false,
				'duplicate' => false
			],
			'sections' => [
				'modules' => [
					'parent' => 'page'
				]
			]
		],
		'modules/changeTemplate' => [
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
