<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Configurables;

use Illuminate\Support\Facades\URL;
use IvanFuhr\Essentials\Contracts\Configurable;
use IvanFuhr\Essentials\Support\ConfigurableConfig;

final readonly class ForceScheme implements Configurable
{
    /**
     * Whether the configurable is enabled or not.
     */
    public function enabled(): bool
    {
        return app()->environment(...$this->environments())
            && ConfigurableConfig::enabled(self::class);
    }

    /**
     * Run the configurable.
     */
    public function configure(): void
    {
        URL::forceHttps();
    }

    /**
     * The environments the configurable should be set for.
     *
     * @return array<string>
     */
    private function environments(): array
    {
        return ConfigurableConfig::environments(self::class);
    }
}
