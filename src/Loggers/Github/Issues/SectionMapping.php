<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues;

use Illuminate\Support\Collection;

final class SectionMapping
{
    private const array SECTION_MAPPINGS = [
        '{simplified_stack_trace}' => 'stacktrace',
        '{full_stack_trace}' => 'stacktrace',
        '{previous_exceptions}' => 'prev-stacktrace',
        '{environment}' => 'environment',
        '{request}' => 'request',
        '{route}' => 'route',
        '{user}' => 'user',
        '{queries}' => 'queries',
        '{job}' => 'job',
        '{command}' => 'command',
        '{outgoing_requests}' => 'outgoing_requests',
        '{breadcrumbs}' => 'breadcrumbs',
        '{session}' => 'session',
        '{livewire}' => 'livewire',
        '{inertia}' => 'inertia',
        '{context}' => 'context',
        '{extra}' => 'extra',
        '{prev_exception_simplified_stack_trace}' => 'prev-exception-stacktrace',
        '{prev_exception_full_stack_trace}' => 'prev-exception-stacktrace',
    ];

    public static function getSectionsToRemove(array $replacements): array
    {
        return collect(self::SECTION_MAPPINGS)
            ->when($replacements === [], fn (Collection $collection) => $collection->values()->unique())
            ->when($replacements !== [], fn (Collection $collection) => $collection
                ->filter(fn (string $_, string $placeholder): bool => isset($replacements[$placeholder]) && empty($replacements[$placeholder]))
                ->values()
                ->unique())
            ->values()
            ->toArray();
    }

    public static function getRemainingSections(array $sectionsToRemove): array
    {
        return collect(self::SECTION_MAPPINGS)
            ->values()
            ->unique()
            ->diff($sectionsToRemove)
            ->values()
            ->toArray();
    }

    public static function getSectionPattern(string $section, bool $removeContent = false): string
    {
        if ($removeContent) {
            return "/<!-- {$section}:start -->.*?<!-- {$section}:end -->\n?/s";
        }

        return sprintf('/<!-- %s:start -->\s*(.*?)\s*<!-- %s:end -->/s', $section, $section);
    }

    public static function getStandaloneFlagPattern(): string
    {
        return '/<!-- (stacktrace|prev-stacktrace|context|extra|prev-exception|prev-exception-stacktrace|environment|request|route|user|queries|job|command|outgoing_requests|breadcrumbs|session|livewire|inertia):(start|end) -->\n?/s';
    }
}
