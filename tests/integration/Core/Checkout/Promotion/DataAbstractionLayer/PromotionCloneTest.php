<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Promotion\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Promotion\PromotionCollection;
use Shopwell\Core\Checkout\Promotion\PromotionEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionCloneTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCloneResetsRedemptionCounters(): void
    {
        $context = Context::createDefaultContext();
        $sourceId = Uuid::randomHex();
        $cloneId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $promotionRepository->create([[
            'id' => $sourceId,
            'name' => 'Used promotion',
            'orderCount' => 3,
            'ordersPerCustomerCount' => [$customerId => 2],
        ]], $context);

        $promotionRepository->clone($sourceId, $context, $cloneId);

        $clone = $promotionRepository->search(new Criteria([$cloneId]), $context)->getEntities()->first();
        static::assertInstanceOf(PromotionEntity::class, $clone);
        static::assertSame(0, $clone->getOrderCount());
        static::assertNull($clone->getOrdersPerCustomerCount());
    }
}
