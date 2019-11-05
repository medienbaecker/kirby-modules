<?php

$blueprints = [];
foreach (kirby()->blueprints() as $blueprint) {
	if(Str::startsWith($blueprint, "module.")) $blueprints[] = $blueprint;
}

Kirby::plugin('medienbaecker/modulehelper', [
    'blueprints' => [
        'modulehelper/create' => [
			'create' => $blueprints
		],
		'modulehelper/changeTemplate' => [
			'changeTemplate' => $blueprints
		]
	]
]);