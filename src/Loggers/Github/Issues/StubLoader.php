<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

final class StubLoader
{
    public function load(string $name): string
    {
        $publishedPath = resource_path(sprintf('views/vendor/essentials/github/%s.md', $name));
        $packagePath = __DIR__.sprintf('/../../../../resources/loggers/github/views/%s.md', $name);

        if (File::exists($publishedPath)) {
            return (string) File::get($publishedPath);
        }

        if (! File::exists($packagePath)) {
            throw new FileNotFoundException('Package stub not found: '.$packagePath);
        }

        return (string) File::get($packagePath);
    }
}
