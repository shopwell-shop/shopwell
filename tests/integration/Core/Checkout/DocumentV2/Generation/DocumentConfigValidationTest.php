<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\DocumentV2\Generation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopwell\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopwell\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentConfigValidationTest extends TestCase
{
    use DocumentV2Trait;

    public function testInvalidConfigurationLeavesNumberAndOrderVersionsUnchanged(): void
    {
        $this->context = Context::createDefaultContext();
        $addressId = Uuid::randomHex();
        $customerId = $this->createCustomer(
            ['defaultShippingAddressId' => $addressId],
            $this->buildDemoShippingAddress($addressId),
        );
        $ids = new IdsCollection();
        $ids->set('customer', $customerId);
        $order = (new OrderBuilder($ids, 'order'))
            ->add('languageId', Defaults::LANGUAGE_SYSTEM)
            ->addAddress('billing_address', [
                'id' => $ids->get('billing_address'),
                'country' => ['id' => $this->getValidCountryId()],
                'salutationId' => $this->getValidSalutationId(),
            ])
            ->orderCustomer('Customer', 'customer')
            ->addTransaction('transaction')
            ->build();
        static::getContainer()->get('order.repository')->create([$order], $this->context);
        $this->seedDemoBaseConfig('invoice');
        static::getContainer()->get(SystemConfigService::class)->set(
            'core.basicInformation.companyCountryId',
            'invalid',
            TestDefaults::SALES_CHANNEL,
        );
        static::getContainer()->get(DocumentConfigLoader::class)->reset();

        $connection = static::getContainer()->get(Connection::class);
        $numberGenerator = static::getContainer()->get(AbstractNumberRangeValueGenerator::class);
        $numberBefore = $numberGenerator->getValue('document_invoice', $this->context, TestDefaults::SALES_CHANNEL, preview: true);
        $versionsBefore = $connection->fetchOne('SELECT COUNT(*) FROM `version`');

        $this->expectExceptionObject(DocumentV2Exception::configMissingRequiredFields(
            DocumentCompanyInfo::class,
            'invoice',
            'companyCountry',
        ));

        try {
            static::getContainer()->get(DocumentGenerator::class)->generate(
                new DocumentGenerationRequest($ids->get('order'), 'invoice', ['html']),
                $this->context,
            );
        } finally {
            static::assertSame($numberBefore, $numberGenerator->getValue('document_invoice', $this->context, TestDefaults::SALES_CHANNEL, preview: true));
            static::assertSame($versionsBefore, $connection->fetchOne('SELECT COUNT(*) FROM `version`'));
            static::assertSame(1, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM `order` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('order'))],
            ));
        }
    }
}
