<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\SalesChannel\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\Cleanup\CleanupSalesChannelContextTaskHandler;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class CleanupSalesChannelContextTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CleanupSalesChannelContextTaskHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = static::getContainer()->get(CleanupSalesChannelContextTaskHandler::class);
    }

    public function testCleanup(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM sales_channel_api_context');

        $ids = new IdsCollection();

        $this->createSalesChannelContext($ids->create('context-1'));

        $date = new \DateTime();
        $date->modify(\sprintf('-%d day', 121));
        $this->createSalesChannelContext($ids->create('context-2'), $date);

        $this->handler->run();

        $contexts = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT token FROM sales_channel_api_context');

        static::assertCount(1, $contexts);
        static::assertContains($ids->get('context-1'), $contexts);
    }

    private function createSalesChannelContext(string $token, ?\DateTime $date = null): void
    {
        $payload = [
            'token' => $token,
            'payload' => json_encode([
                'key' => 'value',
                'expired' => false,
            ]),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ];

        if ($date) {
            $payload['updated_at'] = $date->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        static::getContainer()->get(Connection::class)->insert('sales_channel_api_context', $payload);
    }
}
