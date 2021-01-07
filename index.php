<?php

use Kirby\Cms\Template;

include __DIR__ . '/lib/models.php';
include __DIR__ . '/lib/functions.php';
include __DIR__ . '/lib/collection.php';

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
			foreach ($this->modules() as $module) {
				$moduleTemplate = new Template($module->intendedTemplate());
				echo $moduleTemplate->render([
					'page' => $this,
					'module' => $module,
					'site' => $this->site()
				]);
			}
		},
		'hasModules' => function () {
			$modules = array_filter($this->blueprint()->sections(), function ($section) {
				return 'modules' === $section->type();
			});
			return count($modules) > 0;
		},
		'modules' => function () {
			$modules = new ModulesCollection;
			if ($rawModules = $this->find('modules')) {
				foreach($rawModules->childrenAndDrafts() as $module) {
					if (!$module->isListed() && !$module->isDraft()) continue;
					if ($module->isDraft && !$module->isVerified(get('token'))) continue;
					$modules->append($module);
				}
			}
			return $modules;
		},
		'isModule' => function () {
			return Str::startsWith($this->intendedTemplate(), 'module.');
		}
	],
	'api' => [
		'routes' => include __DIR__ . '/lib/routes.php',
	],
	'translations' => include __DIR__ . '/lib/translations.php',
]);
