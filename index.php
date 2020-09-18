<?php

require_once __DIR__ . '/lib/models/module.php';
require_once __DIR__ . '/lib/functions.php';

dump(moduleRegistry());

Kirby::plugin('medienbaecker/modules', [
	'templates' => moduleRegistry()['templates'],
	'pageModels' => moduleRegistry()['pageModels'],
	'blueprints' => moduleRegistry()['blueprints']
]);
