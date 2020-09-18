<?php

function getModules() {
	$modules = [];
	$modulesFolder = kirby()->root('site') . "/modules";
	foreach (Dir::dirs($modulesFolder) as $folder) {
		if(F::exists($modulesFolder . "/". $folder . "/" . $folder . ".yml")) {
			$modules[] = $folder;
		}
	}
	return $modules;
}