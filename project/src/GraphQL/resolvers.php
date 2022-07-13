<?php

return [
    'Query' => [
        'getCharacteres' => function($root, $args, $context) {
            return $context['db']->fetchAll("SELECT * FROM characteres");
        },
        'getQuotes' => function($root, $args, $context) {
            return $context['db']->fetchAll("SELECT * FROM quotes");
        }
    ]
];