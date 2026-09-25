<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Maintenance\SalesChannel\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelCreatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private SalesChannelCreator $salesChannelCreator;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        $this->salesChannelCreator = static::getContainer()->get(SalesChannelCreator::class);
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');
    }

    public function testCreateSalesChannel(): void
    {
        $id = Uuid::randomHex();
        $this->salesChannelCreator->createSalesChannel($id, 'test', Defaults::SALES_CHANNEL_TYPE_API);

        $salesChannel = $this->salesChannelRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($salesChannel);
        static::assertSame('test', $salesChannel->getName());
        static::assertSame(Defaults::SALES_CHANNEL_TYPE_API, $salesChannel->getTypeId());
    }
}
