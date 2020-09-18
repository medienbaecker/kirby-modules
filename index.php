<?php

include __DIR__ . '/lib/models.php';
include __DIR__ . '/lib/functions.php';

Kirby::plugin('medienbaecker/modules', [
	'templates' => moduleRegistry()['templates'],
	'pageModels' => moduleRegistry()['pageModels'],
	'blueprints' => moduleRegistry()['blueprints'],
	'sections' => [
		'modules' => include __DIR__ . '/lib/sections/modules.php'
	],
	'fields' => [
		'modules_redirect' => include __DIR__ . '/lib/fields/redirect.php'
	],
	'hooks' => [
		'route:after' => include __DIR__ . '/lib/hooks/containerCreator.php'
	],
	'pageMethods' => [
		'renderModules' => function() {
			return 'bratan';
		},
		'isModule' => function () {
			return Str::startsWith($this->intendedTemplate(), 'module.');
		},
		'moduleName' => function () {
			return $this->blueprint()->title();
		},
		'moduleId' => function () {
			return str_replace('.', '_', $this->intendedTemplate());
		},
	]
]);