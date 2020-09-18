<?php

function moduleRegistry() {
	$registry = [];
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
	return $registry;
}