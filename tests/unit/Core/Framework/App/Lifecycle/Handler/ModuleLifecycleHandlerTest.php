<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopwell\Core\Framework\App\Lifecycle\Handler\ModuleLifecycleHandler;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Manifest\Xml\Administration\Admin;
use Shopwell\Core\Framework\App\Manifest\Xml\Administration\MainModule;
use Shopwell\Core\Framework\App\Manifest\Xml\Administration\Module;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ModuleLifecycleHandler::class)]
class ModuleLifecycleHandlerTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testPersistDoesNothingWithoutAppSecret(): void
    {
        $appRepository = new StaticEntityRepository([]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: false));

        static::assertSame([], $appRepository->updates);
    }

    public function testPersistClearsModulesWhenNoAdminSection(): void
    {
        $appRepository = new StaticEntityRepository([]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: true, admin: null));

        static::assertCount(1, $appRepository->updates);
        static::assertSame([[
            'id' => $this->ids->get('app'),
            'mainModule' => null,
            'modules' => [],
        ]], $appRepository->updates[0]);
    }

    public function testPersistModulesWithMainModuleOnly(): void
    {
        $appRepository = new StaticEntityRepository([]);

        $admin = Admin::fromArray([
            'mainModule' => MainModule::fromArray(['source' => 'https://example.com/main']),
            'modules' => [],
        ]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: true, admin: $admin));

        static::assertCount(1, $appRepository->updates);
        static::assertSame([[
            'id' => $this->ids->get('app'),
            'mainModule' => ['source' => 'https://example.com/main'],
            'modules' => [],
        ]], $appRepository->updates[0]);
    }

    public function testPersistModulesWithModulesOnly(): void
    {
        $appRepository = new StaticEntityRepository([]);

        $admin = Admin::fromArray([
            'mainModule' => null,
            'modules' => [
                Module::fromArray([
                    'name' => 'module1',
                    'label' => ['en-GB' => 'Module 1'],
                    'source' => 'https://example.com/module1',
                    'parent' => 'sw-catalogue',
                    'position' => 1,
                ]),
                Module::fromArray([
                    'name' => 'module2',
                    'label' => ['en-GB' => 'Module 2'],
                    'source' => null,
                    'parent' => 'sw-order',
                    'position' => 2,
                ]),
            ],
        ]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: true, admin: $admin));

        static::assertCount(1, $appRepository->updates);
        static::assertSame([[
            'id' => $this->ids->get('app'),
            'mainModule' => null,
            'modules' => [
                [
                    'label' => ['en-GB' => 'Module 1'],
                    'source' => 'https://example.com/module1',
                    'name' => 'module1',
                    'parent' => 'sw-catalogue',
                    'position' => 1,
                ],
                [
                    'label' => ['en-GB' => 'Module 2'],
                    'source' => null,
                    'name' => 'module2',
                    'parent' => 'sw-order',
                    'position' => 2,
                ],
            ],
        ]], $appRepository->updates[0]);
    }

    public function testPersistModulesWithMainModuleAndModules(): void
    {
        $appRepository = new StaticEntityRepository([]);

        $admin = Admin::fromArray([
            'mainModule' => MainModule::fromArray(['source' => 'https://example.com/main']),
            'modules' => [
                Module::fromArray([
                    'name' => 'module1',
                    'label' => ['en-GB' => 'Module 1'],
                    'source' => 'https://example.com/module1',
                    'parent' => 'sw-catalogue',
                    'position' => 1,
                ]),
            ],
        ]);

        $persister = new ModuleLifecycleHandler($appRepository);
        $persister->install($this->buildContext(hasSecret: true, admin: $admin));

        static::assertCount(1, $appRepository->updates);
        static::assertSame([[
            'id' => $this->ids->get('app'),
            'mainModule' => ['source' => 'https://example.com/main'],
            'modules' => [
                [
                    'label' => ['en-GB' => 'Module 1'],
                    'source' => 'https://example.com/module1',
                    'name' => 'module1',
                    'parent' => 'sw-catalogue',
                    'position' => 1,
                ],
            ],
        ]], $appRepository->updates[0]);
    }

    private function buildContext(bool $hasSecret, ?Admin $admin = null): AppPersistContext
    {
        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setActive(true);

        if ($hasSecret) {
            $app->setAppSecret('s3cr3t');
        }

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getAdmin')->willReturn($admin);

        return new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
        );
    }
}
