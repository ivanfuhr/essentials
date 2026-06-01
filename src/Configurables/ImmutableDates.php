<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Configurables;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use IvanFuhr\Essentials\Contracts\Configurable;
use IvanFuhr\Essentials\Support\ConfigurableConfig;

final readonly class ImmutableDates implements Configurable
{
    /**
     * Whether the configurable is enabled or not.
     */
    public function enabled(): bool
    {
        return ConfigurableConfig::enabled(self::class);
    }

    /**
     * Run the configurable.
     */
    public function configure(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
