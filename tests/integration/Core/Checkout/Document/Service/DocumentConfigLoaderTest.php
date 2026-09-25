<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopwell\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopwell\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentConfigLoaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private DocumentConfigLoader $documentConfigLoader;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $customerId = $this->createCustomer();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ]
        );

        $this->documentConfigLoader = static::getContainer()->get(DocumentConfigLoader::class);

        $this->connection = static::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        $this->documentConfigLoader->reset();
    }

    public function testLoadGlobalConfig(): void
    {
        $this->addLogoToDocument('invoice');

        $base = $this->getBaseConfig('invoice');
        $globalConfig = $base === null ? [] : $base->getConfig();
        $globalConfig['companyName'] = 'Test corp.';
        $globalConfig['displayCompanyAddress'] = true;
        $this->upsertBaseConfig($globalConfig, 'invoice');

        $salesChannelId = $this->salesChannelContext->getSalesChannelId();
        $config = $this->documentConfigLoader->load('invoice', $salesChannelId, $this->context);

        $config = $config->jsonSerialize();

        static::assertSame('Test corp.', $config['companyName']);
        static::assertTrue($config['displayCompanyAddress']);
        static::assertNotNull($config['logo']);
        static::assertInstanceOf(MediaEntity::class, $config['logo']);
    }

    public function testLoadSalesChannelConfig(): void
    {
        $base = $this->getBaseConfig('invoice');

        $globalConfig = DocumentConfigurationFactory::createConfiguration([
            'companyName' => 'Test corp.',
            'displayCompanyAddress' => true,
        ], $base);

        $this->upsertBaseConfig($globalConfig->jsonSerialize(), InvoiceRenderer::TYPE);

        $salesChannelConfig = DocumentConfigurationFactory::mergeConfiguration($globalConfig, [
            'companyName' => 'Custom corp.',
            'displayCompanyAddress' => false,
            'pageSize' => 'a5',
        ]);

        $salesChannelId = $this->salesChannelContext->getSalesChannelId();
        $this->upsertBaseConfig($salesChannelConfig->jsonSerialize(), InvoiceRenderer::TYPE, $salesChannelId);

        $config = $this->documentConfigLoader->load(InvoiceRenderer::TYPE, $salesChannelId, $this->context);

        $config = $config->jsonSerialize();

        static::assertSame('Custom corp.', $config['companyName']);
        static::assertFalse($config['displayCompanyAddress']);
        static::assertSame('a5', $config['pageSize']);
    }

    private function addLogoToDocument(string $configName): void
    {
        $qb = $this->connection->createQueryBuilder();

        $mediaId = $qb->select('media.id AS ID')
            ->from('media')
            ->fetchOne();

        $qb->update('document_base_config')
            ->set('document_base_config.logo_id', ':id')
            ->where('document_base_config.name = :name')
            ->setParameter('id', $mediaId)
            ->setParameter('name', $configName)
            ->executeStatement();
    }
}
