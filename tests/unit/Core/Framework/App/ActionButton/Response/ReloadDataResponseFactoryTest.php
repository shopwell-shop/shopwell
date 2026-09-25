<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\ActionButton\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\ActionButton\AppAction;
use Shopwell\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopwell\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopwell\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopwell\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopwell\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Payload\Source;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReloadDataResponseFactory::class)]
class ReloadDataResponseFactoryTest extends TestCase
{
    private ReloadDataResponseFactory $factory;

    private AppAction $action;

    protected function setUp(): void
    {
        $this->factory = new ReloadDataResponseFactory();
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setAppSecret('app-secret');
        $this->action = new AppAction(
            $app,
            new Source('http://shop.url', 'shop-id', '1.0.0'),
            'http://target.url',
            'customer',
            'action-name',
            [Uuid::randomHex(), Uuid::randomHex()],
            'action-it'
        );
    }

    #[DataProvider('provideActionTypes')]
    public function testSupportsOnlyReloadDataActionType(string $actionType, bool $isSupported): void
    {
        static::assertSame($isSupported, $this->factory->supports($actionType));
    }

    public function testCreatesReloadDataResponse(): void
    {
        $response = $this->factory->create($this->action, [], Context::createDefaultContext());

        static::assertInstanceOf(ReloadDataResponse::class, $response);
    }

    /**
     * @return array<int, array<string|bool>>
     */
    public static function provideActionTypes(): array
    {
        return [
            [NotificationResponse::ACTION_TYPE, false],
            [OpenModalResponse::ACTION_TYPE, false],
            [OpenNewTabResponse::ACTION_TYPE, false],
            [ReloadDataResponse::ACTION_TYPE, true],
        ];
    }
}
