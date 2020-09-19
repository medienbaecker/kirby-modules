<?php

use Kirby\Cms\Template;

include __DIR__ . '/lib/models.php';
include __DIR__ . '/lib/functions.php';

$moduleRegistry = createModuleRegistry();

Kirby::plugin('medienbaecker/modules', [
	'templates' => $moduleRegistry['templates'],
	'pageModels' => $moduleRegistry['pageModels'],
	'blueprints' => $moduleRegistry['blueprints'],
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
		'renderModules' => function () {
			if ($modules = $this->find('modules')) {
				foreach ($modules->children()->listed() as $module) {
					$moduleTemplate = new Template($module->intendedTemplate());
					echo $moduleTemplate->render(['module' => $module]);
				}
			}
		},
		'isModule' => function () {
			return Str::startsWith($this->intendedTemplate(), 'module.');
		}
	]
]);