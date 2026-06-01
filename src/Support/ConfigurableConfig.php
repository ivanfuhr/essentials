<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Support;

final class ConfigurableConfig
{
    /**
     * @param  class-string  $configurable
     */
    public static function enabled(string $configurable, bool $default = true): bool
    {
        return config()->boolean(sprintf('essentials.configurables.%s', $configurable), $default);
    }

    /**
     * @param  class-string  $configurable
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    public static function environments(string $configurable, array $default = ['production']): array
    {
        /** @var array<int, string> $environments */
        $environments = config()->array(sprintf('essentials.configurables.environments.%s', $configurable), $default);

        return $environments;
    }
}
