<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Promotion\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexingMessage;
use Shopwell\Core\Checkout\Promotion\PromotionCollection;
use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopwell\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionIndexerTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testPromotionIndexerUpdateReturnNullIfGeneratingCode(): void
    {
        $indexer = static::getContainer()->get(PromotionIndexer::class);

        $salesChannelContext = $this->createSalesChannelContext();

        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');

        /** @var EntityRepository<PromotionIndividualCodeCollection> $promotionIndividualRepository */
        $promotionIndividualRepository = static::getContainer()->get('promotion_individual_code.repository');

        $voucherA = $this->ids->create('voucherA');

        $writtenEvent = $this->createPromotion($voucherA, $voucherA, $promotionRepository, $salesChannelContext);
        $promotionEvent = $writtenEvent->getEventByEntityName(PromotionDefinition::ENTITY_NAME);

        static::assertNotNull($promotionEvent);
        static::assertNotEmpty($promotionEvent->getWriteResults()[0]);
        $promotionId = $promotionEvent->getWriteResults()[0]->getPayload()['id'];

        $userId = Uuid::randomHex();
        $origin = new AdminApiSource($userId);
        $origin->setIsAdmin(true);
        $context = Context::createDefaultContext($origin);

        $event = $this->createIndividualCode($promotionId, 'CODE-1', $promotionIndividualRepository, $context);

        $result = $indexer->update($event);

        static::assertNull($result);
    }

    public function testPromotionIndexerUpdateReturnPromotionIndexingMessage(): void
    {
        $indexer = static::getContainer()->get(PromotionIndexer::class);

        $salesChannelContext = $this->createSalesChannelContext();

        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');

        $voucherA = $this->ids->create('voucherA');

        $writtenEvent = $this->createPromotion($voucherA, $voucherA, $promotionRepository, $salesChannelContext);

        $result = $indexer->update($writtenEvent);

        static::assertInstanceOf(PromotionIndexingMessage::class, $result);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createSalesChannelContext(array $options = []): SalesChannelContext
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $salesChannelContextFactory->create($token, TestDefaults::SALES_CHANNEL, $options);
    }
}
