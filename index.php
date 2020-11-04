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
				foreach ($modules->childrenAndDrafts() as $module) {
					if(!$module->isListed() && !$module->isDraft()) continue;
					if($module->isDraft && !$module->isVerified(get('token'))) continue;
					$moduleTemplate = new Template($module->intendedTemplate());
					echo $moduleTemplate->render([
						'page' => $this,
						'module' => $module,
						'site' => $this->site()
					]);
				}
			}
		},
		'hasModules' => function () {
			$modules = array_filter($this->blueprint()->sections(), function ($section) {
				return 'modules' === $section->type();
			});
			return count($modules) > 0;
		},
		'modules' => function () {
			if ($modules = $this->find('modules')) {
				return $modules->children();
			}
			return [];
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