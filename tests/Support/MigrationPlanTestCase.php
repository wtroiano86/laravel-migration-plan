<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Wtroiano\MigrationPlan\Migrations\OrderedMigrator;
use Wtroiano\MigrationPlan\Repositories\OrderedMigrationRepository;
use Wtroiano\MigrationPlan\Services\MigrationPlan;

abstract class MigrationPlanTestCase extends TestCase
{
    protected string $directory;

    protected MigrationPlan $plan;

    protected OrderedMigrationRepository $repository;

    protected OrderedMigrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/helm-migrations-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        mkdir($this->directory.'/foundation');
        mkdir($this->directory.'/app');
        $container = new Container;
        $capsule = new Manager($container);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);

        $events = new Dispatcher($container);
        $container->instance('db', $capsule->getDatabaseManager());
        $container->bind('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $this->plan = new MigrationPlan;
        $this->plan->register('example/invoices', [$this->directory.'/app'], ['example/customers']);
        $this->plan->register('example/customers', [$this->directory.'/foundation']);

        $this->repository = new OrderedMigrationRepository($capsule->getDatabaseManager(), 'migrations');
        $this->repository->createRepository();

        $this->migrator = new OrderedMigrator($this->repository, $capsule->getDatabaseManager(), new Filesystem, $events, $this->plan);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    protected function migration(string $group, string $name, string $up, ?string $down = null): void
    {
        $rollback = $down === null ? '' : "public function down(): void { {$down} }";
        file_put_contents($this->directory.'/'.$group.'/'.$name.'.php', "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\nreturn new class extends Migration { public function up(): void { {$up} } {$rollback} };\n");
    }
}
