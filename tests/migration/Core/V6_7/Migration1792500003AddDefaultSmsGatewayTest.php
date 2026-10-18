<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1792500003AddDefaultSmsGateway;
use Shopwell\Core\System\Sms\Gateway\Null\NullGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792500003AddDefaultSmsGateway::class)]
class Migration1792500003AddDefaultSmsGatewayTest extends TestCase
{
    private const PROVIDER = 'aliyun';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        // the migration may already have run while the test database was installed, and a run of one
        // test must not hand its rows to the next one
        $this->connection->executeStatement(
            'DELETE FROM `sms_gateway` WHERE `technical_name` = :nullGateway OR `technical_name` = :provider',
            ['nullGateway' => Migration1792500003AddDefaultSmsGateway::TECHNICAL_NAME, 'provider' => self::PROVIDER]
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792500003, (new Migration1792500003AddDefaultSmsGateway())->getCreationTimestamp());
    }

    /**
     * The shop is able to send right after the installation: the baseline gateway is active, serves
     * every sales channel and names the provider the component registered.
     */
    public function testSeedsTheNullGateway(): void
    {
        (new Migration1792500003AddDefaultSmsGateway())->update($this->connection);

        $gateway = $this->nullGateway();
        static::assertIsArray($gateway);
        static::assertSame(NullGatewayTransportFactory::TECHNICAL_NAME, $gateway['technical_name']);
        static::assertSame(1, (int) $gateway['enabled']);
        static::assertNull($gateway['sales_channel_id']);
        static::assertSame(SmsGatewayEntity::STRATEGY_EXCLUSIVE, $gateway['strategy']);
        static::assertSame(Migration1792500003AddDefaultSmsGateway::PRIORITY, (int) $gateway['priority']);
    }

    /**
     * The provider column of the administration already names the gateway, so the seed carries no
     * name of its own that would shadow it.
     */
    public function testTheSeededGatewayCarriesNoNameOfItsOwn(): void
    {
        (new Migration1792500003AddDefaultSmsGateway())->update($this->connection);

        $gateway = $this->nullGateway();
        static::assertIsArray($gateway);
        static::assertNull($gateway['label']);
    }

    /**
     * The unique key of the table spans the sales channel and mysql does not consider two null
     * channels equal, so the migration has to look the row up before it writes.
     */
    public function testSeedingTwiceLeavesOneGateway(): void
    {
        $migration = new Migration1792500003AddDefaultSmsGateway();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(1, $this->countNullGateways());
    }

    /**
     * A gateway an administrator configured carries a normal priority and is therefore picked first;
     * the seeded one is only reached when nothing else matched.
     */
    public function testTheSeededGatewayIsTheLastCandidate(): void
    {
        (new Migration1792500003AddDefaultSmsGateway())->update($this->connection);
        $this->insertGateway(self::PROVIDER, 0);

        $technicalNames = $this->connection->fetchFirstColumn(
            'SELECT `technical_name` FROM `sms_gateway` WHERE `technical_name` = :nullGateway OR `technical_name` = :provider ORDER BY `priority` ASC',
            ['nullGateway' => Migration1792500003AddDefaultSmsGateway::TECHNICAL_NAME, 'provider' => self::PROVIDER]
        );

        static::assertSame([self::PROVIDER, Migration1792500003AddDefaultSmsGateway::TECHNICAL_NAME], $technicalNames);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nullGateway(): ?array
    {
        $gateway = $this->connection->fetchAssociative(
            'SELECT `technical_name`, `label`, `enabled`, `sales_channel_id`, `priority`, `strategy`
                FROM `sms_gateway` WHERE `technical_name` = :technicalName',
            ['technicalName' => Migration1792500003AddDefaultSmsGateway::TECHNICAL_NAME]
        );

        return \is_array($gateway) ? $gateway : null;
    }

    private function countNullGateways(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `sms_gateway` WHERE `technical_name` = :technicalName',
            ['technicalName' => Migration1792500003AddDefaultSmsGateway::TECHNICAL_NAME]
        );
    }

    private function insertGateway(string $technicalName, int $priority): void
    {
        $this->connection->insert('sms_gateway', [
            'id' => Uuid::randomBytes(),
            'technical_name' => $technicalName,
            'enabled' => true,
            'priority' => $priority,
            'strategy' => SmsGatewayEntity::STRATEGY_EXCLUSIVE,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
