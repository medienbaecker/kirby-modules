<?php


function createModuleRegistry() {

	// Set up registry array
	$registry = ['blueprints' => [], 'templates' => [], 'pageModels' => []];
	
	// Add modules in site/modules
	$modulesFolder = kirby()->root('site') . "/modules";
	foreach (Dir::dirs($modulesFolder) as $folder) {
		$blueprint = $modulesFolder . "/". $folder . "/" . $folder . ".yml";
		$template = $modulesFolder . "/". $folder . "/" . $folder . ".php";
		if(F::exists($blueprint)) {
			$blueprintArray = Yaml::read($blueprint);
			if(!array_key_exists('status', $blueprintArray)) {
				$blueprintArray['status'] = [
					'draft' => true,
					'listed' => true,
				];
			}
			$registry['blueprints']['pages/module.'. $folder] = $blueprintArray;
			$registry['templates']['module.'. $folder] = $template;
			$registry['pageModels']['module.'. $folder] = option('medienbaecker.modules.model', 'ModulePage');
		}
	}
	
	// Add legacy modules to registry
	$moduleBlueprints = array_filter(kirby()->blueprints(), function($blueprint) {
		return Str::startsWith($blueprint, 'module.');
	});
	if(!empty($moduleBlueprints)) {
		$blueprintsFolder = kirby()->root('blueprints');
		$snippetsFolder = kirby()->root('snippets');
		foreach($moduleBlueprints as $moduleBlueprint) {
			$blueprint = $blueprintsFolder . "/pages/" . $moduleBlueprint . ".yml";
			$template = $snippetsFolder . "/modules/" . $moduleBlueprint . ".php";
			if(F::exists($blueprint)) {
				$blueprintArray = Yaml::read($blueprint);
				if(!array_key_exists('status', $blueprintArray)) {
					$blueprintArray['status'] = [
						'draft' => true,
						'listed' => true,
					];
				}
				if(!array_key_exists('pages/'. $moduleBlueprint, $registry['blueprints'])) {
					$registry['blueprints']['pages/'. $moduleBlueprint] = $blueprintArray;	
				}
				if(!array_key_exists($moduleBlueprint, $registry['templates'])) {
					$registry['templates'][$moduleBlueprint] = $template;
				}
				if(!array_key_exists($moduleBlueprint, $registry['pageModels'])) {
					$registry['pageModels'][$moduleBlueprint] = option('medienbaecker.modules.model', 'ModulePage');
				}
			}
		}
	}
	
	// Add modules container blueprint with redirect field
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
	
	// Add modules container model
	$registry['pageModels']['modules'] = 'ModulesPage';
	
	
	return $registry;
}