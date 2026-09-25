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
        $emptySearchResult = new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            $context->getContext()
        );
        $repository->method('search')->willReturn($emptySearchResult);

        $validator = static::callback(static fn ($subject) => $subject instanceof ProductGatewayCriteriaEvent);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with($validator);

        $gateway = new ProductGateway(
            $repository,
            $eventDispatcher
        );

        $gateway->get($ids, $context);
    }
}
