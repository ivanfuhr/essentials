<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Configurables;

use Illuminate\Database\Eloquent\Model;
use IvanFuhr\Essentials\Contracts\Configurable;
use IvanFuhr\Essentials\Support\ConfigurableConfig;

final readonly class Unguard implements Configurable
{
    /**
     * Whether the configurable is enabled or not.
     */
    public function enabled(): bool
    {
        return ConfigurableConfig::enabled(self::class, false);
    }

    /**
     * Run the configurable.
     */
    public function configure(): void
    {
        Model::unguard();
    }
}
