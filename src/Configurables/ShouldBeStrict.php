<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Configurables;

use Illuminate\Database\Eloquent\Model;
use IvanFuhr\Essentials\Contracts\Configurable;
use IvanFuhr\Essentials\Support\ConfigurableConfig;

final readonly class ShouldBeStrict implements Configurable
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
        Model::shouldBeStrict();
    }
}
