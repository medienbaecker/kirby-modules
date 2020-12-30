<?php

return [
	'computed' => [
		'redirect' => function () {
			if($this->model()->isHomePage()) {
				return $this->model()->site()->panelUrl();	
			}
			else {
				return $this->model()->parent()->panelUrl();	
			}
		}
	]
];