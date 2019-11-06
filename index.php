<?php

$blueprints = [];
foreach (kirby()->blueprints() as $blueprint) {
	if(Str::startsWith($blueprint, 'module.')) $blueprints[] = $blueprint;
}

Kirby::plugin('medienbaecker/modulehelper', [
	'blueprints' => [
		'modulehelper/create' => [
			'create' => $blueprints
		],
		'modulehelper/changeTemplate' => [
			'changeTemplate' => $blueprints
		]
	],
	'pageMethods' => [
		'isModule' => function () {
			return Str::startsWith($this->intendedTemplate(), 'module.')
		},
		'moduleName' => function () {
			return str_replace('.', '__', $this->intendedTemplate());
		},
		'moduleDisplayName' => function () {
			return $this->blueprint()->title();
		}
	]
]);
