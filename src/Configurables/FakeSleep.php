<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Configurables;

use Illuminate\Support\Sleep;
use IvanFuhr\Essentials\Contracts\Configurable;
use IvanFuhr\Essentials\Support\ConfigurableConfig;

final readonly class FakeSleep implements Configurable
{
    /**
     * Whether the configurable is enabled or not.
     */
    public function enabled(): bool
    {
        $enabled = ConfigurableConfig::enabled(self::class);
        $testing = app()->runningUnitTests();

        return $enabled && $testing;
    }

    /**
     * Run the configurable.
     */
    public function configure(): void
    {
        Sleep::fake();
    }
}
