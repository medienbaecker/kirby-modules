<?php


function createModuleRegistry() {

	$registry = ['blueprints' => [], 'templates' => [], 'pageModels' => []];
	$modulesFolder = kirby()->root('site') . "/modules";
	foreach (Dir::dirs($modulesFolder) as $folder) {
		$blueprint = $modulesFolder . "/". $folder . "/" . $folder . ".yml";
		$template = $modulesFolder . "/". $folder . "/" . $folder . ".php";
		if(F::exists($blueprint) AND F::exists($template)) {
			$registry['blueprints']['pages/module.'. $folder] = $blueprint;
			$registry['templates']['module.'. $folder] = $template;
			$registry['pageModels']['module.'. $folder] = 'ModulePage';
		}
	}
	$registry['blueprints']['pages/modules'] = [
		'title' => 'Modules',
		'options' => [
			'changeTemplate' => false,
			'delete' => false
		],
		'fields' => [
			'modules_redirect' => true
		]
	];
	$registry['pageModels']['modules'] = 'ModulesPage';
	return $registry;
}