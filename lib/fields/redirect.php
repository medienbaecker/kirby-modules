<?php

return [
	'computed' => [
		'redirect' => function () {
			return $this->model()->parent()->panelUrl();
		}
	]
];