<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\ActionButton;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\ActionButton\AppActionLoader;
use Shopwell\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class AppActionLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testCreateAppActionReturnCorrectData(): void
    {
        $actionLoader = static::getContainer()->get(AppActionLoader::class);

        /** @var EntityRepository<ActionButtonCollection> $actionRepo */
        $actionRepo = static::getContainer()->get('app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app')
            ->addAssociation('app.integration');

        $actionCollection = $actionRepo->search($criteria, Context::createDefaultContext())->getEntities();
        $action = $actionCollection->first();
        static::assertNotNull($action);

        $shopIdProvider = static::getContainer()->get(ShopIdProvider::class);

        $ids = [Uuid::randomHex()];
        $result = $actionLoader->loadAppAction($action->getId(), $ids, Context::createDefaultContext());

        $app = $action->getApp();

        static::assertNotNull($app);

        $expected = [
            'source' => [
                'url' => getenv('APP_URL'),
                'appVersion' => $app->getVersion(),
                'shopId' => $shopIdProvider->getShopId(),
                'inAppPurchases' => null,
            ],
            'data' => [
                'ids' => $ids,
                'entity' => $action->getEntity(),
                'action' => $action->getAction(),
            ],
        ];

        static::assertEquals($expected, $result->asPayload());
        static::assertSame($action->getUrl(), $result->getTargetUrl());
    }
}
