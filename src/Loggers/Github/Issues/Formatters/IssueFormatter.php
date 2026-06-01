<?php

declare(strict_types=1);

namespace IvanFuhr\Essentials\Loggers\Github\Issues\Formatters;

use IvanFuhr\Essentials\Loggers\Github\Issues\TemplateRenderer;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use RuntimeException;

final class IssueFormatter implements FormatterInterface
{
    public function __construct(
        private readonly TemplateRenderer $templateRenderer,
    ) {}

    public function format(LogRecord $record): Formatted
    {
        if (! isset($record->extra['github_issue_signature'])) {
            throw new RuntimeException('Record is missing github_issue_signature in extra data. Make sure the DeduplicationHandler is configured correctly.');
        }

        return new Formatted(
            title: $this->templateRenderer->renderTitle($record),
            body: $this->templateRenderer->render($this->templateRenderer->getIssueStub(), $record, $record->extra['github_issue_signature']),
            comment: $this->templateRenderer->render($this->templateRenderer->getCommentStub(), $record, null),
        );
    }

    public function formatBatch(array $records): array
    {
        return array_map([$this, 'format'], $records);
    }
}
