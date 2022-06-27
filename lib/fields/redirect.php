<?php

return [
    'computed' => [
        'redirect' => function () {
            if ($this->model()->isHomePage()) {
                return $this->model()
                    ->site()
                    ->panel()
                    ->url();
            } else {
                return $this->model()
                    ->parent()
                    ->panel()
                    ->url();
            }
        }
    ]
];
