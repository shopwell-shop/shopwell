<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopwell\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppService;
use Shopwell\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopwell\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopwell\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\AppSystemTestBehaviour;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
class AppServiceTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private AppService $appService;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private Context $context;

    /**
     * @var EntityRepository<ActionButtonCollection>
     */
    private EntityRepository $actionButtonRepository;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->actionButtonRepository = static::getContainer()->get('app_action_button.repository');

        $this->appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                $this->getAppLoader(__DIR__ . '/Manifest/_fixtures/test')
            ),
            static::getContainer()->get(AppLifecycle::class)
        );

        $this->context = Context::createDefaultContext();
    }

    public function testRefreshInstallsNewApp(): void
    {
        $this->appService->doRefreshApps(new AppInstallParameters(), $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $first = $apps->first();
        static::assertInstanceOf(AppEntity::class, $first);
        static::assertSame('test', $first->getName());

        $this->assertDefaultActionButtons();
    }

    public function testRefreshUpdatesApp(): void
    {
        $this->appRepository->create([[
            'name' => 'test',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'test',
            ],
        ]], $this->context);

        $this->appService->doRefreshApps(new AppInstallParameters(), $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $first = $apps->first();
        static::assertInstanceOf(AppEntity::class, $first);
        static::assertSame('test', $first->getName());
        static::assertSame('1.0.0', $first->getVersion());
        static::assertNotSame('test', $first->getTranslation('label'));

        $this->assertDefaultActionButtons();
    }

    public function testRefreshAppIsUntouched(): void
    {
        $this->appRepository->create([[
            'name' => 'test',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '1.0.0',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'test',
            ],
        ]], $this->context);

        $this->appService->doRefreshApps(new AppInstallParameters(), $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        $first = $apps->first();
        static::assertInstanceOf(AppEntity::class, $first);
        static::assertSame('test', $first->getName());
        static::assertSame('1.0.0', $first->getVersion());
        static::assertSame('test', $first->getTranslation('label'));
    }

    public function testRefreshDeletesApp(): void
    {
        $appId = Uuid::randomHex();
        $this->appRepository->create([[
            'id' => $appId,
            'name' => 'deleteTest',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'deleteTest',
            ],
        ]], $this->context);

        static::assertCount(1, $this->appRepository->searchIds(new Criteria(), $this->context)->getIds());

        $this->appService->doRefreshApps(new AppInstallParameters(), $this->context);

        $apps = $this->appRepository->searchIds(new Criteria([$appId]), $this->context)->getIds();
        static::assertCount(0, $apps);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $apps = $this->actionButtonRepository->searchIds($criteria, $this->context)->getIds();
        static::assertCount(0, $apps);
    }

    public function testGetRefreshableApps(): void
    {
        $this->appRepository->create([
            [
                'name' => 'deleteTest',
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'actionButtons' => [
                    [
                        'entity' => 'order',
                        'view' => 'detail',
                        'action' => 'test',
                        'label' => 'test',
                        'url' => 'test.com',
                    ],
                ],
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'deleteTest',
                ],
            ],
            [
                'name' => 'test',
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'actionButtons' => [
                    [
                        'entity' => 'order',
                        'view' => 'detail',
                        'action' => 'test',
                        'label' => 'test',
                        'url' => 'test.com',
                    ],
                ],
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'test',
                ],
            ],
        ], $this->context);

        $appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                $this->getAppLoader(__DIR__ . '/Manifest/_fixtures')
            ),
            static::getContainer()->get(AppLifecycle::class)
        );
        $refreshableApps = $appService->getRefreshableAppInfo($this->context);

        static::assertCount(8, $refreshableApps->getToBeInstalled());
        static::assertCount(1, $refreshableApps->getToBeUpdated());
        static::assertCount(1, $refreshableApps->getToBeDeleted());

        static::assertInstanceOf(Manifest::class, array_values($refreshableApps->getToBeInstalled())[0]);
        static::assertInstanceOf(Manifest::class, array_values($refreshableApps->getToBeUpdated())[0]);
        static::assertSame('deleteTest', array_values($refreshableApps->getToBeDeleted())[0]);
    }

    public function testInstallFailureDoesNotAffectAllApps(): void
    {
        $appDir = __DIR__ . '/Manifest/_fixtures';
        $finder = new Finder();
        $finder->in($appDir)
            ->depth('<= 1')
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            $manifests[] = $xml->getPathname();
        }

        $appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                $this->getAppLoader($appDir)
            ),
            static::getContainer()->get(AppLifecycle::class)
        );

        $fails = $appService->doRefreshApps(new AppInstallParameters(), $this->context);
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(9, $manifests); // 2 are not parsable (withRequirements still passes as requirements are not asserted in test env)
        static::assertCount(7, $apps);
        static::assertCount(2, $fails);
    }

    private function assertDefaultActionButtons(): void
    {
        $actionButtons = $this->actionButtonRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $actionButtons);

        $actionNames = $actionButtons->map(static fn (ActionButtonEntity $actionButton) => $actionButton->getAction());

        static::assertContains('viewOrder', $actionNames);
        static::assertContains('doStuffWithProducts', $actionNames);
    }
}
