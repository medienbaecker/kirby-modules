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
	'pageMethods' => [
		'renderModules' => function () {
			if ($modules = $this->find('modules')) {
				foreach ($modules->children()->listed() as $module) {
					$moduleTemplate = new Template($module->intendedTemplate());
					echo $moduleTemplate->render(['module' => $module]);
				}
			}
		},
		'hasModules' => function () {
			$modules = array_filter($this->blueprint()->sections(), function ($section) {
				return 'modules' === $section->type();
			});
			return count($modules) > 0;
		},
		'isModule' => function () {
			return Str::startsWith($this->intendedTemplate(), 'module.');
		}
	],
	'api' => [
		'routes' => include __DIR__ . '/lib/routes.php',
	],
]);