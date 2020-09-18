<?php

return function ($route, $path, $method) {
	$uid = explode('/', $path);
	$uid = end($uid);
	$uid = str_replace('+', '/', $uid);
	$page = kirby()->page($uid);
	if ($page) {
		if(!$page->find('modules') AND $page->intendedTemplate() != 'modules') {
			if($page->blueprint()->section('modules')) {
				kirby()->impersonate('kirby');
				try {
					$modulesPage = $page->createChild([
						'content'  => ['title' => 'Modules'],
						'slug'     => 'modules',
						'template' => 'modules'
					]);
				}
				catch (Exception $error) {
					throw new Exception($error);
				}
				if($modulesPage) {
					$modulesPage->publish();
				}
			}
		}
	}
};