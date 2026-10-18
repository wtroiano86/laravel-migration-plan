<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Pipelines;

use Closure;
use Illuminate\Pipeline\Pipeline;
use ReflectionMethod;
use Wtroiano\MigrationPlan\Actions\ValidateRollback;
use Wtroiano\MigrationPlan\Data\RollbackPlan;
use Wtroiano\MigrationPlan\Exceptions\IrreversibleMigration;

final readonly class PrepareRollback
{
    public function __construct(private ValidateRollback $validate = new ValidateRollback) {}

    /** @param Closure(string): object $resolve */
    public function handle(RollbackPlan $rollback, Closure $resolve): RollbackPlan
    {
        /** @var RollbackPlan $prepared */
        $prepared = (new Pipeline)->send($rollback)
            ->through([$this->validateSelection(...)])
            ->then(fn (RollbackPlan $validated): RollbackPlan => $this->prepare($validated, $resolve));

        return $prepared;
    }

    /** @param Closure(RollbackPlan): RollbackPlan $next */
    private function validateSelection(RollbackPlan $rollback, Closure $next): RollbackPlan
    {
        $this->validate->handle($rollback);

        return $next($rollback);
    }

    /** @param Closure(string): object $resolve */
    private function prepare(RollbackPlan $rollback, Closure $resolve): RollbackPlan
    {
        foreach ($rollback->selected as $name) {
            $file = $rollback->plan->files[$name];
            $instance = $resolve($file->path);
            throw_unless(method_exists($instance, 'down'), IrreversibleMigration::class, $file);
            $method = new ReflectionMethod($instance, 'down');
            throw_if(! $method->isPublic() || $method->getNumberOfRequiredParameters() > 0 || (string) $method->getReturnType() === 'never', IrreversibleMigration::class, $file);
        }

        $owners = array_flip($rollback->plan->owners());
        $execution = array_flip($rollback->ran);
        $files = $rollback->plan->files;
        $selected = $rollback->selected;
        usort($selected, fn (string $left, string $right): int => ($owners[$files[$right]->owner] <=> $owners[$files[$left]->owner])
            ?: ($execution[$right] <=> $execution[$left]));

        return new RollbackPlan($rollback->plan, $selected, $rollback->ran);
    }
}
