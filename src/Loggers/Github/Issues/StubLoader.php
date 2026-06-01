<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

final class StubLoader
{
    public function load(string $name): string
    {
        $publishedPath = resource_path("views/vendor/essentials/github/{$name}.md");
        $packagePath = __DIR__."/../../../../resources/loggers/github/views/{$name}.md";

        if (File::exists($publishedPath)) {
            return (string) File::get($publishedPath);
        }

        if (! File::exists($packagePath)) {
            throw new FileNotFoundException("Package stub not found: {$packagePath}");
        }

        return (string) File::get($packagePath);
    }
}
