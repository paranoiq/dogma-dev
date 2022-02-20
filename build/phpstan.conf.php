<?php declare(strict_types=1);

$ignore = PHP_VERSION_ID < 80000
    ? [

    ]
    : [

    ];

return ['parameters' => ['ignoreErrors' => $ignore]];