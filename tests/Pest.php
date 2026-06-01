<?php

declare(strict_types=1);

use Tests\TestCase;

pest()->use(TestCase::class)->in(
    'Configurables',
    'Commands',
    'Support',
    'EssentialsServiceProviderTest.php',
);

pest()->use(Tests\Loggers\Github\TestCase::class)->in('Loggers/Github');

require __DIR__.'/helpers.php';
require __DIR__.'/Loggers/Github/helpers.php';
