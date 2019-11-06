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
	], 'pageMethods' => [
		'isModule'          => function () {
			return Str::startsWith($this->intendedTemplate(), 'module.')
		},
		'moduleName'        => function () {
			return str_replace('.', '__', $this->intendedTemplate());
		},
		'moduleDisplayName' => function () {
			if ($this->isModule())
				return ucwords(str_replace('-', ' ', substr($this->intendedTemplate(), 7)));
			else
				return ucwords($this->intendedTemplate());
		}
	]
]);
