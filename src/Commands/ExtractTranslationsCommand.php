<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ExtractTranslationsCommand extends Command
{
    protected $signature = 'translations:extract {directory? : Base directory to search (default: base_path())} {lang? : Language code (optional)}';

    protected $description = 'Extract translation strings and generate or update a JSON file with the found keys.';

    public function handle(): int
    {
        $directoryArg = $this->argument('directory');
        $langArg = $this->argument('lang');

        $baseDir = is_string($directoryArg) ? $directoryArg : base_path();

        /** @var string $lang */
        $lang = is_string($langArg) ? $langArg : $this->ask('Please enter the language code you want to extract', 'en');

        $this->info('Scanning directory: '.$baseDir);

        $translationKeys = $this->scanDirectory($baseDir);

        if ($translationKeys === []) {
            $this->info('No translation strings found.');

            return self::SUCCESS;
        }

        $translationsPath = base_path(sprintf('lang/%s.json', $lang));
        $existingTranslations = [];

        if (file_exists($translationsPath)) {
            $content = file_get_contents($translationsPath);

            if (is_string($content)) {
                $decoded = json_decode($content, true);

                if (is_array($decoded)) {
                    $existingTranslations = $decoded;
                }
            }
        }

        foreach ($translationKeys as $key) {
            if (! array_key_exists($key, $existingTranslations)) {
                $existingTranslations[$key] = '';
            }
        }

        ksort($existingTranslations);

        $jsonData = json_encode($existingTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $langDirectory = dirname($translationsPath);

        if (! is_dir($langDirectory)) {
            mkdir($langDirectory, 0755, true);
        }

        if ($jsonData !== false && file_put_contents($translationsPath, $jsonData) !== false) {
            $this->info(sprintf('File %s successfully updated!', $translationsPath));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function getFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && preg_match('/\.(php|blade\.php)$/', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function extractTranslations(string $content): array
    {
        $keys = [];
        $patterns = [
            '/__\(\s*[\'"]((?:[^\'"\\\\]|\\\\.)+)[\'"]/',
            '/trans\(\s*[\'"]((?:[^\'"\\\\]|\\\\.)+)[\'"]/',
            '/trans_choice\(\s*[\'"]((?:[^\'"\\\\]|\\\\.)+)[\'"]/',
            '/@lang\(\s*[\'"]((?:[^\'"\\\\]|\\\\.)+)[\'"]/',
            '/#\[Title\(\s*[\'"]((?:[^\'"\\\\]|\\\\.)+)[\'"]\)\]/',
            '/#\[Validate\(.*?as:\s*[\'"]((?:[^\'"\\\\]|\\\\.)+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $keys[] = stripcslashes($match);
                }
            }
        }

        return $keys;
    }

    /**
     * @return array<int, string>
     */
    private function scanDirectory(string $dir): array
    {
        $files = $this->getFiles($dir);
        $totalFiles = count($files);
        $allKeys = [];

        foreach ($files as $index => $file) {
            $percentage = number_format((($index + 1) / max($totalFiles, 1)) * 100, 2);
            $this->line(sprintf('[%s%%] Processing: %s', $percentage, $file));

            /** @var string $content */
            $content = file_get_contents($file);

            $keys = $this->extractTranslations($content);

            if ($keys !== []) {
                $allKeys = array_merge($allKeys, $keys);
            }
        }

        $allKeys = array_unique($allKeys);
        sort($allKeys);

        return $allKeys;
    }
}
