<?php declare(strict_types=1);

return PHP_VERSION_ID >= 80000
    ? ['parameters' => ['ignoreErrors' => [
        '~Parameter #1 \$socket of function socket_.* expects Socket, resource\|Socket given~',
        '~Static property DogmaDebugTools::\$socket \(resource\|Socket\) does not accept Socket\|false.~',
    ]]]
    : [];
