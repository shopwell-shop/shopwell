<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\MailTemplate\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopwell\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopwell\Core\Checkout\Document\Service\DocumentGenerator;
use Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopwell\Core\Content\MailTemplate\MailTemplateCollection;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('after-sales')]
class MailActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testSendSuccess(): void
    {
        $context = Context::createDefaultContext();

        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer');
        $order = static::getContainer()->get('order.repository')->search($criteria, $context)->getEntities()->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        $documentId = $this->createDocumentWithFile($orderId, $context);
        $documentIds = [$documentId];

        $criteria = new Criteria();
        $criteria->setLimit(1);
        /** @var ?MailTemplateEntity $mailTemplate */
        $mailTemplate = static::getContainer()
            ->get('mail_template.repository')
            ->search($criteria, $context)->getEntities()
            ->first();
        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        $criteria = new Criteria([TestDefaults::SALES_CHANNEL]);
        $criteria->setLimit(1);
        $salesChannel = static::getContainer()
            ->get('sales_channel.repository')
            ->search($criteria, $context)->getEntities()
            ->first();
        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        $entityEncoder = new JsonEntityEncoder(
            new Serializer([new StructNormalizer()], [new JsonEncoder()])
        );
        $orderDefinition = static::getContainer()->get(OrderDefinition::class);
        $orderDecode = $entityEncoder->encode(new Criteria(), $orderDefinition, $order, '/api');
        array_walk_recursive($orderDecode, static function (&$value): void {
            if ($value instanceof \stdClass) {
                $value = json_decode((string) json_encode($value), true, 512, \JSON_THROW_ON_ERROR);
            }
        });

        $salesChannelDefinition = static::getContainer()->get(SalesChannelDefinition::class);
        $salesChannelDecode = $entityEncoder->encode(new Criteria(), $salesChannelDefinition, $salesChannel, '/api');
        array_walk_recursive($salesChannelDecode, static function (&$value): void {
            if ($value instanceof \stdClass) {
                $value = json_decode((string) json_encode($value), true, 512, \JSON_THROW_ON_ERROR);
            }
        });

        $this->getBrowser()
            ->request(
                'POST',
                '/api/_action/mail-template/send',
                [
                    'contentHtml' => $mailTemplate->getContentHtml(),
                    'contentPlain' => $mailTemplate->getContentPlain(),
                    'mailTemplateData' => [
                        'order' => $orderDecode,
                        'salesChannel' => $salesChannelDecode,
                    ],
                    'documentIds' => $documentIds,
                    'recipients' => ['d.dinh@shopwell.cn' => 'Duy'],
                    'salesChannelId' => $salesChannel->getId(),
                    'senderName' => $salesChannel->getName(),
                    'subject' => 'New document for your order',
                    'testMode' => false,
                ],
            );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('size', $response);
    }

    public function testPreviewSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/preview',
            [
                'mailTemplateId' => $mailTemplate->getId(),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'includeHeaderFooter' => true,
                'templateData' => [
                    'customName' => 'Shopwell',
                ],
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertSame('success', $response['subject']['type']);
        static::assertSame('Hello Shopwell', $response['subject']['content']);
        static::assertSame('success', $response['contentHtml']['type']);
        static::assertStringContainsString('Shopwell', $response['contentHtml']['content']);
    }

    public function testGetDataAndSendSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/get-data-and-send',
            [
                'mailTemplateId' => $mailTemplate->getId(),
                'templateData' => [
                    'customName' => 'Shopwell',
                ],
                'recipients' => ['d.dinh@shopwell.cn' => 'Duy'],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'testMode' => false,
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('size', $response);
        static::assertGreaterThan(0, $response['size']);
    }

    public function testSimulateSuccess(): void
    {
        TestUser::createNewTestUser(
            $this->getBrowser()->getContainer()->get(Connection::class),
            ['mail_template:update']
        )->authorizeBrowser($this->getBrowser());

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/simulate',
            [
                'templateParts' => [
                    'contentHtml' => '<p>{{ order.id }}</p>',
                ],
                'eventName' => 'checkout.order.placed',
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertSame('success', $response['contentHtml']['type']);
        static::assertNotSame('', $response['contentHtml']['content']);
    }

    public function testSimulateRequiresMailTemplateUpdatePrivilege(): void
    {
        $browser = $this->getBrowser();
        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['mail_template:read']
        )->authorizeBrowser($browser);

        $browser->request('POST', '/api/_action/mail-template/simulate');

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
    }

    public function testAvailableVariablesSuccess(): void
    {
        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/available-variables',
            [
                'eventName' => 'checkout.order.placed',
                'parentVariablePath' => 'order',
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertContains('lineItems', array_column($response, 'fieldName'));
    }

    private function createCustomer(Context $context): string
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
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopwell',
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

        static::getContainer()
            ->get('customer.repository')
            ->upsert([$customer], $context);

        return $customerId;
    }

    private function createOrder(string $customerId, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
                    'id' => $billingAddressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [
            ],
            'transactions' => [
                [
                    'paymentMethodId' => $this->getValidPaymentMethodId(),
                    'stateId' => $stateId,
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
            'context' => '{}',
            'payload' => '{}',
        ];

        $orderRepository = static::getContainer()->get('order.repository');

        $orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createDocumentWithFile(string $orderId, Context $context, string $documentType = InvoiceRenderer::TYPE): string
    {
        $documentGenerator = static::getContainer()->get(DocumentGenerator::class);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, []);
        $document = $documentGenerator->generate($documentType, [$orderId => $operation], $context)->getSuccess()->first();

        static::assertNotNull($document);

        return $document->getId();
    }

    private function createSimpleMailTemplate(Context $context): MailTemplateEntity
    {
        $typeCriteria = new Criteria();
        $typeCriteria->setLimit(1);

        /** @var EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = static::getContainer()->get('mail_template_type.repository');
        $mailTemplateType = $mailTemplateTypeRepository->search($typeCriteria, $context)->getEntities()->first();

        static::assertInstanceOf(MailTemplateTypeEntity::class, $mailTemplateType);

        $mailTemplateId = Uuid::randomHex();

        /** @var EntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $mailTemplateRepository->create([[
            'id' => $mailTemplateId,
            'mailTemplateTypeId' => $mailTemplateType->getId(),
            'subject' => 'Hello {{ customName }}',
            'senderName' => 'Shopwell',
            'contentHtml' => '<p>Hello {{ customName }}</p>',
            'contentPlain' => 'Hello {{ customName }}',
        ]], $context);

        $mailTemplate = $mailTemplateRepository->search(new Criteria([$mailTemplateId]), $context)->getEntities()->first();

        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        return $mailTemplate;
    }
}
