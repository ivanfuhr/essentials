<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Deduplication;

use Illuminate\Support\Str;

final class VendorFrameDetector
{
    /**
     * Check if a trace array frame is a vendor frame
     *
     * @param  array<string, mixed>  $frame
     */
    public function isVendorFrame(array $frame): bool
    {
        $file = $frame['file'] ?? null;

        if (! is_string($file) || $file === '') {
            return false;
        }

        // Check for vendor directory
        if (str_contains($file, '/vendor/')) {
            // Special case: BoundMethod.php calling App code should not be considered vendor
            if (str_contains($file, 'BoundMethod.php')) {
                $function = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');
                if (Str::isMatch('/App/', $function)) {
                    return false;
                }
            }

            return true;
        }

        // Check for artisan
        if (str_contains($file, '/artisan')) {
            return true;
        }

        // Check for {main} function
        $function = $frame['function'] ?? '';

        return $function === '{main}';
    }

    /**
     * Check if a formatted stack trace line is a vendor frame
     */
    public function isVendorFrameLine(string $line): bool
    {
        if (str_contains($line, '/vendor/') && ! Str::isMatch('/BoundMethod\\.php\\(\\d+\\): App/', $line)) {
            return true;
        }

        if (str_contains($line, '/artisan')) {
            return true;
        }

        return str_ends_with($line, '{main}');
    }
}
