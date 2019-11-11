<?php

use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\Query;
use Kirby\Cms\Section;

$base = Section::$types['pages'];

return array_replace_recursive($base, [
    'props' => [
        
    ]
]);