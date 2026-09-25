<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\AppUrlChangeResolver;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopwell\Core\Framework\App\Lifecycle\AppManager;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Test\AppSystemTestBehaviour;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
class ReinstallAppsStrategyTest extends TestCase
{
    use AppSystemTestBehaviour;
    use EnvTestBehaviour;
    use IntegrationTestBehaviour;

    private ShopIdProvider $shopIdProvider;

    private Context $context;

    protected function setUp(): void
    {
        $this->shopIdProvider = static::getContainer()->get(ShopIdProvider::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGetName(): void
    {
        $reinstallAppsResolver = static::getContainer()->get(ReinstallAppsStrategy::class);

        static::assertSame(
            ReinstallAppsStrategy::STRATEGY_NAME,
            $reinstallAppsResolver->getName()
        );
        static::assertIsString($reinstallAppsResolver->getDescription());
    }

    public function testItReRegistersInstalledApps(): void
    {
        $appDir = (string) realpath(__DIR__ . '/../Manifest/_fixtures/test');
        $this->loadAppsFromDir($appDir);

        $app = $this->getInstalledApp($this->context);

        $shopId = $this->changeAppUrl();

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('reregister')
            ->with(
                $app,
                static::isInstanceOf(Context::class)
            );

        $reinstallAppsResolver = new ReinstallAppsStrategy(
            static::getContainer()->get('app.repository'),
            $appManager,
            $this->shopIdProvider,
            new NullLogger()
        );

        $reinstallAppsResolver->resolve($this->context);

        static::assertNotSame($shopId, $this->shopIdProvider->getShopId()->id);
    }

    public function testItDelegatesAppsWithoutSetupToAppManager(): void
    {
        $appDir = __DIR__ . '/../Lifecycle/Registration/_fixtures/no-setup';
        $this->loadAppsFromDir($appDir);

        $shopId = $this->changeAppUrl(false);

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('reregister');

        $reinstallAppsResolver = new ReinstallAppsStrategy(
            static::getContainer()->get('app.repository'),
            $appManager,
            $this->shopIdProvider,
            new NullLogger()
        );

        $reinstallAppsResolver->resolve($this->context);

        static::assertNotSame($shopId, $this->shopIdProvider->getShopId()->id);
    }

    public function testItContinuesWithOtherAppsWhenOneReinstallFails(): void
    {
        $testApp = $this->createAppEntity('test', 'app-1');
        $withConfigApp = $this->createAppEntity('withConfig', 'app-2');

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('reregister')
            ->willReturnCallback(static function () use (&$calls): void {
                ++$calls;

                if ($calls === 1) {
                    throw new \RuntimeException('Could not reach app server');
                }
            });

        $appRepository = new StaticEntityRepository([
            new AppCollection([$testApp, $withConfigApp]),
        ]);

        $reinstallAppsResolver = new ReinstallAppsStrategy(
            $appRepository,
            $appManager,
            $this->shopIdProvider,
            new NullLogger()
        );

        $this->expectExceptionObject(AppException::reinstallAppsFailed(['test']));

        $reinstallAppsResolver->resolve($this->context);
    }

    private function changeAppUrl(bool $expectToThrow = true): string
    {
        $shopId = $this->shopIdProvider->getShopId();

        // create AppUrlChange
        $this->setEnvVars(['APP_URL' => 'https://test.new']);
        $wasThrown = false;

        try {
            $this->shopIdProvider->reset();
            $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException) {
            $wasThrown = true;
        }
        static::assertSame($expectToThrow, $wasThrown);

        return $shopId->id;
    }

    private function getInstalledApp(Context $context): AppEntity
    {
        /** @var EntityRepository<AppCollection> $appRepo */
        $appRepo = static::getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('integration');
        $app = $appRepo->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($app);

        return $app;
    }

    private function createAppEntity(string $name, string $id): AppEntity
    {
        $app = new AppEntity();
        $app->setId($id);
        $app->setName($name);

        return $app;
    }
}
