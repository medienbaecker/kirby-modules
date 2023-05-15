<?php

return [
  'computed' => [
    'redirect' => function () {
      return $this->model()->parents()->count() > 0 ? $this->model()->parents()->first()->panel()->url() : $this->model()->site()->panel()->url();
    }
  ]
];
