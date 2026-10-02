<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopwell\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopwell\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopwell\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopwell\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopwell\Core\Checkout\DocumentV2\DocumentCollection;
use Shopwell\Core\Checkout\DocumentV2\DocumentEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @phpstan-type OrderSettings array{accountType: string, isCountryCompanyTaxFree: bool, setOrderDelivery: bool, setShippingCountry: bool, setEuCountry: bool, shouldCheckVatIdPattern?: bool, validVat?: bool}
 * @phpstan-type InvoiceConfig array{displayAdditionalNoteDelivery: bool, fileTypes: array<string>}
 */
#[Package('after-sales')]
#[CoversClass(InvoiceRenderer::class)]
class InvoiceRendererTest extends TestCase
{
    private const COUNTRY_ID = 'country-id';

    /**
     * @param OrderSettings $orderSettings
     * @param InvoiceConfig $config
     */
    #[DataProvider('configDataProvider')]
    public function testRenderIsAllowIntraCommunityDelivery(
        array $orderSettings,
        array $config,
        bool $expectedResult
    ): void {
        $context = Context::createDefaultContext();

        $order = $this->createOrder($orderSettings);
        $orderId = $order->getId();
        $orderCollection = new OrderCollection([$order]);
        $orderSearchResult = new EntitySearchResult(OrderDefinition::ENTITY_NAME, 1, $orderCollection, null, new Criteria(), $context);

        $documentConfigSearchResult = $this->createDocumentConfigSearchResult($config, $context);

        $documentConfigRepository = static::createStub(EntityRepository::class);
        $documentConfigRepository->method('search')->willReturn($documentConfigSearchResult);

        $documentConfigLoaderMock = new DocumentConfigLoader($documentConfigRepository, static::createStub(EntityRepository::class));

        $ordersLanguageId = [
            [
                'language_id' => Defaults::LANGUAGE_SYSTEM,
                'ids' => $orderId,
            ],
        ];
        $connectionMock = static::createStub(Connection::class);
        $connectionMock->method('fetchAllAssociative')->willReturn($ordersLanguageId);

        $orderRepositoryMock = static::createStub(EntityRepository::class);
        $orderRepositoryMock->method('search')->willReturn($orderSearchResult);

        $validator = static::createStub(ValidatorInterface::class);
        if (isset($orderSettings['shouldCheckVatIdPattern']) && $orderSettings['shouldCheckVatIdPattern']) {
            $validator->method('validate')->willReturnCallback(static function () use ($orderSettings) {
                if ($orderSettings['validVat'] ?? false) {
                    return new ConstraintViolationList();
                }

                return new ConstraintViolationList(
                    [
                        new ConstraintViolation(
                            'VAT ID is invalid',
                            null,
                            [],
                            'vat',
                            'vatId',
                            'invalid'
                        ),
                    ],
                );
            });
        }

        $invoiceRenderer = new InvoiceRenderer(
            $orderRepositoryMock,
            $documentConfigLoaderMock,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $connectionMock,
            static::createStub(DocumentFileRendererRegistry::class),
            $validator,
            new NativeClock()
        );

        $operations = [
            $orderId => new DocumentGenerateOperation(
                $orderId
            ),
        ];

        $result = $invoiceRenderer->render($operations, $context, new DocumentRendererConfig());

        $successResults = $result->getSuccess();
        static::assertCount(1, $successResults);
        static::assertCount(0, $result->getErrors());
        static::assertArrayHasKey($orderId, $successResults);

        static::assertNotNull($successResults[$orderId]->getOrder());
        static::assertNotNull($successResults[$orderId]->getContext());
        static::assertSame($successResults[$orderId]->getTemplate(), '@Framework/documents/invoice.html.twig');

        if ($expectedResult) {
            static::assertTrue($successResults[$orderId]->getConfig()['intraCommunityDelivery']);
        } else {
            static::assertFalse($successResults[$orderId]->getConfig()['intraCommunityDelivery']);
        }
    }

    public function testLanguageIdChainAssignedCorrectly(): void
    {
        $context = Context::createDefaultContext();

        $order = $this->createOrder([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'isCountryCompanyTaxFree' => true,
            'setOrderDelivery' => true,
            'setShippingCountry' => true,
            'setEuCountry' => true,
        ]);

        $orderId = $order->getId();
        $orderCollection = new OrderCollection([$order]);
        $orderSearchResult = new EntitySearchResult(OrderDefinition::ENTITY_NAME, 1, $orderCollection, null, new Criteria(), $context);

        $DELanguageId = Uuid::randomHex();

        $ordersLanguageId = [
            [
                'language_id' => $DELanguageId,
                'ids' => $orderId,
            ],
            [
                'language_id' => Defaults::LANGUAGE_SYSTEM,
                'ids' => $orderId,
            ],
        ];

        $connectionMock = static::createStub(Connection::class);
        $connectionMock->method('fetchAllAssociative')->willReturn($ordersLanguageId);

        $userCallCount = 0;

        $orderRepositoryMock = static::createStub(EntityRepository::class);
        $orderRepositoryMock->method('search')->willReturnCallback(static function (Criteria $criteria, Context $context) use (&$userCallCount, $DELanguageId, $orderSearchResult) {
            ++$userCallCount;

            switch ($userCallCount) {
                case 1:
                    static::assertCount(2, $context->getLanguageIdChain());
                    static::assertContains(Defaults::LANGUAGE_SYSTEM, $context->getLanguageIdChain());
                    static::assertContains($DELanguageId, $context->getLanguageIdChain());

                    break;
                case 2:
                    static::assertCount(1, $context->getLanguageIdChain());
                    static::assertContains(Defaults::LANGUAGE_SYSTEM, $context->getLanguageIdChain());
            }

            return $orderSearchResult;
        });

        $invoiceRenderer = new InvoiceRenderer(
            $orderRepositoryMock,
            new DocumentConfigLoader(static::createStub(EntityRepository::class), static::createStub(EntityRepository::class)),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $connectionMock,
            static::createStub(DocumentFileRendererRegistry::class),
            static::createStub(ValidatorInterface::class),
            new NativeClock()
        );

        $operations = [
            $orderId => new DocumentGenerateOperation(
                $orderId
            ),
        ];

        $invoiceRenderer->render($operations, $context, new DocumentRendererConfig());
    }

    public function testDoNotForceDocumentCreation(): void
    {
        $context = Context::createDefaultContext();

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());

        $order = $this->createOrder([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'isCountryCompanyTaxFree' => true,
            'setOrderDelivery' => true,
            'setShippingCountry' => true,
            'setEuCountry' => true,
        ]);

        $order->setDocuments(new DocumentCollection([$document]));

        $orderId = $order->getId();
        $orderCollection = new OrderCollection([$order]);
        $orderSearchResult = new EntitySearchResult(OrderDefinition::ENTITY_NAME, 1, $orderCollection, null, new Criteria(), $context);

        $connectionMock = static::createStub(Connection::class);
        $connectionMock->method('fetchAllAssociative')->willReturn([
            [
                'language_id' => Defaults::LANGUAGE_SYSTEM,
                'ids' => $orderId,
            ],
        ]);

        $orderRepositoryMock = static::createStub(EntityRepository::class);
        $orderRepositoryMock->method('search')->willReturn($orderSearchResult);

        $documentConfigLoaderMock = new DocumentConfigLoader(static::createStub(EntityRepository::class), static::createStub(EntityRepository::class));

        $invoiceRenderer = new InvoiceRenderer(
            $orderRepositoryMock,
            $documentConfigLoaderMock,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $connectionMock,
            static::createStub(DocumentFileRendererRegistry::class),
            static::createStub(ValidatorInterface::class),
            new NativeClock()
        );

        $operations = [
            $orderId => new DocumentGenerateOperation(
                $orderId,
                FileTypes::PDF,
                ['forceDocumentCreation' => false],
            ),
        ];

        $result = $invoiceRenderer->render($operations, $context, new DocumentRendererConfig());

        $successResults = $result->getSuccess();

        static::assertCount(0, $successResults);
    }

    public static function configDataProvider(): \Generator
    {
        yield 'will return true because all necessary configs are made' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'isCountryCompanyTaxFree' => true,
                'setOrderDelivery' => true,
                'setShippingCountry' => true,
                'setEuCountry' => true,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => true,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => true,
        ];

        yield 'will return false because customer is no B2B customer' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
                'isCountryCompanyTaxFree' => true,
                'setOrderDelivery' => true,
                'setShippingCountry' => true,
                'setEuCountry' => true,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => true,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => false,
        ];

        yield 'will return false because country setting "CompanyTaxFree" is not activated' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'isCountryCompanyTaxFree' => false,
                'setOrderDelivery' => true,
                'setShippingCountry' => true,
                'setEuCountry' => true,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => true,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => false,
        ];

        yield 'will return false because customer address is not part of "Member countries"' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'isCountryCompanyTaxFree' => true,
                'setOrderDelivery' => true,
                'setShippingCountry' => true,
                'setEuCountry' => false,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => true,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => false,
        ];

        yield 'will return false because "intra-Community delivery" label is not activated' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'isCountryCompanyTaxFree' => true,
                'setOrderDelivery' => true,
                'setShippingCountry' => true,
                'setEuCountry' => true,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => false,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => false,
        ];

        yield 'will return false because no order-deliveries exist' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'isCountryCompanyTaxFree' => true,
                'setOrderDelivery' => false,
                'setShippingCountry' => false,
                'setEuCountry' => true,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => true,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => false,
        ];

        yield 'will return false because no shipping-country is set' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'isCountryCompanyTaxFree' => true,
                'setOrderDelivery' => true,
                'setShippingCountry' => false,
                'setEuCountry' => true,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => true,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => false,
        ];

        yield 'will return false because VAT is invalid' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'isCountryCompanyTaxFree' => true,
                'setOrderDelivery' => true,
                'setShippingCountry' => true,
                'setEuCountry' => true,
                'shouldCheckVatIdPattern' => true,
                'validVat' => false,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => true,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => false,
        ];

        yield 'will return true because VAT is valid' => [
            'orderSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'isCountryCompanyTaxFree' => true,
                'setOrderDelivery' => true,
                'setShippingCountry' => true,
                'setEuCountry' => true,
                'shouldCheckVatIdPattern' => true,
                'validVat' => true,
            ],
            'config' => [
                'displayAdditionalNoteDelivery' => true,
                'fileTypes' => ['pdf', 'html'],
            ],
            'expectedResult' => true,
        ];
    }

    /**
     * @param OrderSettings $orderSettings
     */
    private function createOrder(array $orderSettings): OrderEntity
    {
        $orderDeliverId = Uuid::randomHex();

        $salesChannelId = Uuid::randomHex();
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChannelId);

        $language = new LanguageEntity();
        $language->setId('language-test-id');
        $localeEntity = new LocaleEntity();
        $localeEntity->setCode('en-GB');
        $language->setLocale($localeEntity);

        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setVersionId(Defaults::LIVE_VERSION);
        $order->setSalesChannelId($salesChannelId);
        $order->setLanguage($language);
        $order->setLanguageId('language-test-id');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setAccountType($orderSettings['accountType']);
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setOrder($order);
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setVatIds(['VAT123']);
        $order->setOrderCustomer($orderCustomer);
        $order->setPrimaryOrderDeliveryId($orderDeliverId);

        if ($orderSettings['setOrderDelivery']) {
            $delivery = new OrderDeliveryEntity();
            $delivery->setId($orderDeliverId);
            $deliveries = new OrderDeliveryCollection([$delivery]);
            $order->setDeliveries($deliveries);
            $order->setPrimaryOrderDelivery($delivery);
        }

        if ($orderSettings['setShippingCountry'] && $orderSettings['setOrderDelivery']) {
            $country = new CountryEntity();
            $country->setId(self::COUNTRY_ID);
            if ($orderSettings['setEuCountry']) {
                $country->setIsEu(true);
            } else {
                $country->setIsEu(false);
            }
            $country->setCompanyTax(new TaxFreeConfig($orderSettings['isCountryCompanyTaxFree'], Defaults::CURRENCY, 0));
            $address = new OrderAddressEntity();
            $address->setCountry($country);
            $country->setCheckVatIdPattern($orderSettings['shouldCheckVatIdPattern'] ?? false);
            $delivery->setShippingOrderAddress($address);
        }

        return $order;
    }

    /**
     * @param InvoiceConfig $config
     *
     * @return EntitySearchResult<DocumentBaseConfigCollection>
     */
    private function createDocumentConfigSearchResult(array $config, Context $context): EntitySearchResult
    {
        $documentBaseConfigEntity = new DocumentBaseConfigEntity();
        $documentBaseConfigEntity->setId(Uuid::randomHex());

        $documentBaseConfigSalesChannel = new DocumentBaseConfigSalesChannelEntity();
        $documentBaseConfigSalesChannel->setId(Uuid::randomHex());

        $documentBaseConfigEntity->setSalesChannels(new DocumentBaseConfigSalesChannelCollection([$documentBaseConfigSalesChannel]));
        $documentBaseConfigEntity->setConfig($config);
        $documentBaseConfigCollection = new DocumentBaseConfigCollection([$documentBaseConfigEntity]);

        return new EntitySearchResult(
            DocumentBaseConfigDefinition::ENTITY_NAME,
            1,
            $documentBaseConfigCollection,
            null,
            new Criteria(),
            $context
        );
    }
}
