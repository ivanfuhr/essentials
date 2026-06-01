<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Configurables;

use Illuminate\Database\Eloquent\Model;
use IvanFuhr\Essentials\Contracts\Configurable;
use IvanFuhr\Essentials\Support\ConfigurableConfig;

final readonly class AutomaticallyEagerLoadRelationships implements Configurable
{
    public function __construct(
        private string $modelClass = Model::class
    ) {}

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
        if (! method_exists($this->modelClass, 'automaticallyEagerLoadRelationships')) {
            return;
        }

        Model::automaticallyEagerLoadRelationships();
    }
}
