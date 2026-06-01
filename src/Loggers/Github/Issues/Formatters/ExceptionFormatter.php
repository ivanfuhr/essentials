<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues\Formatters;

use Illuminate\Support\Str;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use ReflectionClass;
use Throwable;

final readonly class ExceptionFormatter implements FormatterInterface
{
    private const int TITLE_MAX_LENGTH = 100;

    public function __construct(
        private StackTraceFormatter $stackTraceFormatter,
    ) {}

    public function format(LogRecord $record): array
    {
        $exceptionData = $record->context['exception'] ?? null;

        // Handle case where the exception is stored as a string instead of a Throwable object
        if (is_string($exceptionData) &&
            (str_contains($exceptionData, 'Stack trace:') || preg_match('/#\d+ \//', $exceptionData))) {

            return $this->formatExceptionString($exceptionData);
        }

        // Original code for Throwable objects
        if (! $exceptionData instanceof Throwable) {
            return [];
        }

        $message = $this->formatMessage($exceptionData->getMessage());
        $stackTrace = $exceptionData->getTraceAsString();

        $header = $this->formatHeader($exceptionData);

        return [
            'message' => $message,
            'simplified_stack_trace' => $header."\n[stacktrace]\n".$this->stackTraceFormatter->format($stackTrace, true),
            'full_stack_trace' => $header."\n[stacktrace]\n".$this->stackTraceFormatter->format($stackTrace, false),
        ];
    }

    public function formatBatch(array $records): array
    {
        return array_map($this->format(...), $records);
    }

    public function formatTitle(Throwable $exception, string $level): string
    {
        $exceptionClass = (new ReflectionClass($exception))->getShortName();
        $file = Str::replace(base_path(), '', $exception->getFile());

        return Str::of('[{level}] {class} in {file}:{line} - {message}')
            ->replace('{level}', $level)
            ->replace('{class}', $exceptionClass)
            ->replace('{file}', $file)
            ->replace('{line}', (string) $exception->getLine())
            ->replace('{message}', Str::limit($exception->getMessage(), self::TITLE_MAX_LENGTH))
            ->toString();
    }

    private function formatMessage(string $message): string
    {
        // Remove exception class prefix if present (e.g., "RuntimeException: ")
        if (preg_match('/^[A-Z][a-zA-Z0-9_\\\\]+Exception: (.+)$/s', $message, $matches)) {
            $message = mb_trim($matches[1]);
        }

        if (! str_contains($message, 'Stack trace:')) {
            return $message;
        }

        return (string) preg_replace('/\s+in\s+\/[^\s]+\.php:\d+.*$/s', '', $message);
    }

    private function formatHeader(Throwable $exception): string
    {
        return sprintf(
            '[%s] %s: %s at %s:%d',
            now()->format('Y-m-d H:i:s'),
            (new ReflectionClass($exception))->getShortName(),
            $exception->getMessage(),
            str_replace(base_path(), '', $exception->getFile()),
            $exception->getLine()
        );
    }

    /**
     * Format an exception stored as a string.
     */
    private function formatExceptionString(string $exceptionString): array
    {
        $message = $exceptionString;
        $stackTrace = '';

        // Try to extract the message and stack trace
        if (! preg_match('/^(.*?)(?:Stack trace:|#\d+ \/)/s', $exceptionString, $matches)) {
            $header = sprintf(
                '[%s] Exception: %s at unknown:0',
                now()->format('Y-m-d H:i:s'),
                $message
            );

            return [
                'message' => $this->formatMessage($message),
                'simplified_stack_trace' => $header."\n[stacktrace]\n".$this->stackTraceFormatter->format($stackTrace, true),
                'full_stack_trace' => $header."\n[stacktrace]\n".$this->stackTraceFormatter->format($stackTrace, false),
            ];
        }

        $message = mb_trim($matches[1]);

        // Remove exception class prefix if present (e.g., "RuntimeException: ")
        if (preg_match('/^[A-Z][a-zA-Z0-9_\\\\]+Exception: (.+)$/s', $message, $classMatches)) {
            $message = mb_trim($classMatches[1]);
        }

        // Remove file/line info if present (handles both " on line X" and ":X" formats)
        if (preg_match('/^(.*) in \/[^\s]+(?:\.php)?(?::\d+| on line \d+)$/s', $message, $fileMatches)) {
            $message = mb_trim($fileMatches[1]);
        }

        // Extract stack trace
        $traceStart = mb_strpos($exceptionString, 'Stack trace:');
        if ($traceStart !== false) {
            // Find the position after "Stack trace:" and any whitespace/newlines
            $stackTraceStart = $traceStart + mb_strlen('Stack trace:');
            $stackTrace = mb_substr($exceptionString, $stackTraceStart);
            // Remove leading whitespace and newlines
            $stackTrace = mb_ltrim($stackTrace, " \t\n\r\0\x0B");
        } elseif (preg_match('/#\d+ \//', $exceptionString, $traceMatches, PREG_OFFSET_CAPTURE)) {
            $stackTrace = mb_substr($exceptionString, $traceMatches[0][1]);
        } else {
            $stackTrace = '';
        }

        $header = sprintf(
            '[%s] Exception: %s at unknown:0',
            now()->format('Y-m-d H:i:s'),
            $message
        );

        return [
            'message' => $this->formatMessage($message),
            'simplified_stack_trace' => $header."\n[stacktrace]\n".$this->stackTraceFormatter->format($stackTrace, true),
            'full_stack_trace' => $header."\n[stacktrace]\n".$this->stackTraceFormatter->format($stackTrace, false),
        ];
    }
}
