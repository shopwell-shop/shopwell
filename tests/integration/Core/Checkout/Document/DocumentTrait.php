<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Document;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopwell\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopwell\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopwell\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopwell\Core\Checkout\Document\DocumentIdCollection;
use Shopwell\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopwell\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopwell\Core\Checkout\Document\Service\DocumentGenerator;
use Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('after-sales')]
trait DocumentTrait
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    #[After]
    public function resetDocumentConfigLoader(): void
    {
        static::getContainer()->get(DocumentConfigLoader::class)->reset();
    }

    private function persistCart(Cart $cart): string
    {
        return static::getContainer()->get(CartService::class)->order($cart, $this->salesChannelContext, new RequestDataBag());
    }

    /**
     * @param array<string, string> $options
     * @param array<string, string> $additionalAddress
     */
    private function createCustomer(array $options = [], ?array $additionalAddress = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'guest' => true,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        if ($additionalAddress) {
            $customer['addresses'][] = \array_merge($additionalAddress, [
                'customerId' => $customerId,
            ]);
        }

        $customer = \array_merge($customer, $options);

        static::getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $customerId;
    }

    private function generateDemoCart(int $lineItemCount): Cart
    {
        $cartService = static::getContainer()->get(CartService::class);

        $cart = $cartService->createNew('a-b-c');

        $keywords = ['awesome', 'epic', 'high quality'];

        $products = [];

        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        for ($i = 0; $i < $lineItemCount; ++$i) {
            $price = random_int(100, 200000) / 100.0;

            shuffle($keywords);
            $name = ucfirst(implode(' ', $keywords) . ' product');

            $number = Uuid::randomHex();

            $product = (new ProductBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-' . Uuid::randomHex(), 7)
                ->visibility()
                ->build();

            $products[] = $product;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->salesChannelContext);
            $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);
        }

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        return $cartService->add($cart, $lineItems, $this->salesChannelContext);
    }

    /**
     * @param array<int|string, int> $taxes
     */
    private function generateDemoCartWithTaxes(array $taxes): Cart
    {
        $cartService = static::getContainer()->get(CartService::class);

        $cart = $cartService->createNew('A');

        $products = [];

        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        foreach ($taxes as $index => $tax) {
            $price = 100.0 + (int) $index;
            $name = 'product ' . $index;
            $number = 'p' . $index;

            $product = (new ProductBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-' . Uuid::randomHex(), $tax)
                ->visibility()
                ->build();

            $products[] = $product;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->salesChannelContext);
            $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);
        }

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        return $cartService->add($cart, $lineItems, $this->salesChannelContext);
    }

    private function createShippingMethod(float $price = 10.0): string
    {
        $shippingMethodId = Uuid::randomHex();
        $repository = static::getContainer()->get('shipping_method.repository');

        $repository->create([[
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method',
            'technicalName' => Uuid::randomHex(),
            'bindShippingfree' => false,
            'active' => true,
            'salesChannels' => [
                ['id' => TestDefaults::SALES_CHANNEL],
            ],
            'salesChannelDefaultAssignments' => [
                ['id' => TestDefaults::SALES_CHANNEL],
            ],
            'prices' => [[
                'name' => 'Std',
                'currencyPrice' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'net' => $price,
                    'gross' => $price,
                    'linked' => false,
                ]],
                'currencyId' => Defaults::CURRENCY,
                'calculation' => 1,
                'quantityStart' => 1,
            ]],
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'min' => 1,
                'max' => 90,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
        ]], $this->context);

        return $shippingMethodId;
    }

    private function getBaseConfig(string $documentType, ?string $salesChannelId = null): ?DocumentBaseConfigEntity
    {
        /** @var EntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = static::getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $documentType)),
            Context::createDefaultContext()
        )->firstId();

        /** @var EntityRepository<DocumentBaseConfigCollection> $documentBaseConfigRepository */
        $documentBaseConfigRepository = static::getContainer()->get('document_base_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        $criteria->addFilter(new EqualsFilter('global', true));

        if ($salesChannelId !== null) {
            $criteria->addFilter(new EqualsFilter('salesChannels.salesChannelId', $salesChannelId));
            $criteria->addFilter(new EqualsFilter('salesChannels.documentTypeId', $documentTypeId));
        }

        $config = $documentBaseConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if ($config === null) {
            return null;
        }

        static::assertInstanceOf(DocumentBaseConfigEntity::class, $config);

        return $config;
    }

    /**
     * @param array<string, array<string, string>|string> $config
     */
    private function createDocument(string $documentType, string $orderId, array $config, Context $context): DocumentIdCollection
    {
        $operations = [];
        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $config);
        $operations[$orderId] = $operation;

        return static::getContainer()->get(DocumentGenerator::class)->generate($documentType, $operations, $context)->getSuccess();
    }

    /**
     * @param array<string|bool, string|bool|int|array<int, string>> $config
     */
    private function upsertBaseConfig(array $config, string $documentType, ?string $salesChannelId = null): void
    {
        $baseConfig = $this->getBaseConfig($documentType, $salesChannelId);

        /** @var EntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = static::getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $documentType)),
            Context::createDefaultContext()
        )->firstId();

        if ($baseConfig === null) {
            $documentConfigId = Uuid::randomHex();
        } else {
            $documentConfigId = $baseConfig->getId();
        }

        $data = [
            'id' => $documentConfigId,
            'typeId' => $documentTypeId,
            'documentTypeId' => $documentTypeId,
            'config' => $config,
        ];
        if ($baseConfig === null) {
            $data['name'] = $documentConfigId;
        }
        if ($salesChannelId !== null) {
            $data['salesChannels'] = [
                [
                    'documentBaseConfigId' => $documentConfigId,
                    'documentTypeId' => $documentTypeId,
                    'salesChannelId' => $salesChannelId,
                ],
            ];
        }

        /** @var EntityRepository<DocumentBaseConfigCollection> $documentBaseConfigRepository */
        $documentBaseConfigRepository = static::getContainer()->get('document_base_config.repository');
        $documentBaseConfigRepository->upsert([$data], Context::createDefaultContext());
    }

    private function upsertDocumentSellerAddress(string $documentType): void
    {
        $this->upsertBaseConfig([
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
            'companyCountryId' => $this->getValidCountryId(),
        ], $documentType);
    }

    private function orderVersionExists(string $orderId, string $orderVersionId): bool
    {
        return (bool) static::getContainer()->get(Connection::class)->fetchOne('
            SELECT 1 FROM `order` WHERE `id` = :id AND `version_id` = :versionId
        ', [
            'id' => Uuid::fromHexToBytes($orderId),
            'versionId' => Uuid::fromHexToBytes($orderVersionId),
        ]);
    }
}
