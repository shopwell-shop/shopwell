<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Cart\ProductGateway;
use Shopwell\Core\Content\Product\Events\ProductGatewayCriteriaEvent;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductGateway::class)]
class ProductGatewayTest extends TestCase
{
    public function testSendCriteriaEvent(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $context = Generator::generateSalesChannelContext();

        $repository = static::createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(self::createEmptySearchResult($context));

        $validator = static::callback(static fn ($subject) => $subject instanceof ProductGatewayCriteriaEvent);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with($validator);

        $gateway = new ProductGateway(
            $repository,
            $eventDispatcher
        );

        $gateway->get($ids, $context);
    }

    public function testLoadsTheManufacturerForTheGaranLabel(): void
    {
        $context = Generator::generateSalesChannelContext();

        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (Criteria $criteria): bool => $criteria->hasAssociation('manufacturer')))
            ->willReturn(self::createEmptySearchResult($context));

        $gateway = new ProductGateway($repository, static::createStub(EventDispatcherInterface::class));

        $gateway->get([Uuid::randomHex()], $context);
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private static function createEmptySearchResult(SalesChannelContext $context): EntitySearchResult
    {
        return new EntitySearchResult('product', 0, new ProductCollection(), null, new Criteria(), $context->getContext());
    }
}
