<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Tracing;

use Illuminate\Support\Facades\Process;
use Throwable;

class GitInfoDetector
{
    /**
     * Cached git information (null means not yet resolved).
     *
     * @var array<string, string|bool|null>|null
     */
    private static ?array $cachedGitInfo = null;

    /**
     * Reset the cached git information.
     *
     * Useful for testing or when the working directory changes.
     */
    public static function resetCache(): void
    {
        self::$cachedGitInfo = null;
    }

    /**
     * Detect git information for the current working directory.
     *
     * Results are cached statically so git commands only run once per process.
     *
     * @return array<string, string|bool|null>
     */
    public function detect(): array
    {
        if (self::$cachedGitInfo !== null) {
            return self::$cachedGitInfo;
        }

        self::$cachedGitInfo = [];

        $hash = $this->runGitCommand('git log --pretty="%h" -n1 HEAD');
        if ($hash !== null) {
            self::$cachedGitInfo['git_hash'] = $hash;
        }

        $branch = $this->runGitCommand('git rev-parse --abbrev-ref HEAD');
        if ($branch !== null) {
            self::$cachedGitInfo['git_branch'] = $branch;
        }

        $tag = $this->runGitCommand('git describe --tags --abbrev=0 2>/dev/null');
        if ($tag !== null) {
            self::$cachedGitInfo['git_tag'] = $tag;
        }

        $porcelain = $this->runGitCommand('git status --porcelain');
        if ($porcelain !== null) {
            self::$cachedGitInfo['git_dirty'] = $porcelain !== '';
        }

        return self::$cachedGitInfo;
    }

    /**
     * Run a git command with a 1-second timeout.
     *
     * Returns the trimmed output on success, or null on failure.
     */
    private function runGitCommand(string $command): ?string
    {
        try {
            $result = Process::timeout(1)->path(base_path())->run($command);

            return $result->successful() ? mb_trim($result->output()) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
