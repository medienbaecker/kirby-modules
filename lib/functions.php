<?php


function createModuleRegistry() {

	$registry = ['blueprints' => [], 'templates' => [], 'pageModels' => []];
	$modulesFolder = kirby()->root('site') . "/modules";
	foreach (Dir::dirs($modulesFolder) as $folder) {
		$blueprint = $modulesFolder . "/". $folder . "/" . $folder . ".yml";
		$template = $modulesFolder . "/". $folder . "/" . $folder . ".php";
		if(F::exists($blueprint) AND F::exists($template)) {
			$blueprintArray = Yaml::read($blueprint);
			if(!array_key_exists('erstes', $blueprintArray)) {
				$blueprintArray['status'] = [
					'draft' => [
						'label' => I18n::translate('page.status.draft'),
						'text' =>  I18n::translate('page.status.draft.description'),
					],
					'listed' => [
						'label' => I18n::translate('page.status.listed'),
						'text' =>  I18n::translate('page.status.listed.description'),
					],
				];
			}
			$registry['blueprints']['pages/module.'. $folder] = $blueprintArray;
			$registry['templates']['module.'. $folder] = $template;
			$registry['pageModels']['module.'. $folder] = option('medienbaecker.modules.model', 'ModulePage');
		}
	}
	$registry['blueprints']['pages/modules'] = [
		'title' => 'Modules',
		'options' => [
			'changeSlug' => false,
			'changeStatus' => false,
			'changeTemplate' => false
		],
		'fields' => [
			'modules_redirect' => true
		]
	];
	$registry['pageModels']['modules'] = 'ModulesPage';
	return $registry;
}