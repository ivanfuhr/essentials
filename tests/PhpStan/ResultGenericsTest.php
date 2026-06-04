<?php

declare(strict_types=1);

namespace Tests\PhpStan;

use IvanFuhr\Essentials\Result\Result;
use RuntimeException;

enum CreateProjectFailure
{
    case UnableToCreate;
}

final class Project
{
    public string $name = '';
}

final class CreateProjectAction
{
    /**
     * @return Result<Project, CreateProjectFailure>
     */
    public function handle(bool $ok): Result
    {
        if ($ok) {
            return success(new Project);
        }

        return fail(CreateProjectFailure::UnableToCreate);
    }
}

final class ResultGenericsConsumer
{
    /**
     * @param  Result<Project, CreateProjectFailure>  $result
     */
    public function consume(Result $result): void
    {
        $result
            ->whenSuccessful(function (Project $project): void {
                $project->name = 'ok';
            })
            ->whenFailed(CreateProjectFailure::UnableToCreate, function (): void {});

        if ($result->successful()) {
            $result->value()->name = 'typed';
        }

        if ($result->failed()) {
            $result->failure();
        }
    }

    /**
     * @param  Result<Project, CreateProjectFailure>  $result
     */
    public function consumeTyped(Result $result): Project
    {
        if ($result->failed()) {
            throw new RuntimeException('failed');
        }

        return $result->value();
    }
}
