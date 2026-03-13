<?php

return [
  'make:module' => [
    'description' => 'Creates a new module',
    'args' => [
      'name' => [
        'description' => 'The name of the module',
      ]
    ],
    'command' => static function ($cli): void {
      $kirby = $cli->kirby();
      $name  = $cli->argOrPrompt('name', 'Enter a name for the module:');

      $blueprintFile  = $kirby->root('blueprints') . '/modules/' . $name . '.yml';
      $snippetFile = $kirby->root('snippets') . '/modules/' . $name . '.php';

      $cli->make($blueprintFile, 'title: {{ title }}', [
        'title' => ucfirst($name)
      ]);

      $cli->make($snippetFile, '<!-- {{ title }} -->', [
        'title' => ucfirst($name)
      ]);

      $cli->success('The module has been created');
    }
  ]
];
