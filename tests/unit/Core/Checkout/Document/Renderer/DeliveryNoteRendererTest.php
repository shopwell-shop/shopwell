<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Document\DocumentException;
use Shopwell\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopwell\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopwell\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopwell\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation;
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
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DeliveryNoteRenderer::class)]
class DeliveryNoteRendererTest extends TestCase
{
    public function testRenderCreatesNewOrderVersion(): void
    {
        $context = Context::createDefaultContext();

        $documentConfigLoaderMock = new DocumentConfigLoader(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class)
        );

        $order = $this->createOrder();
        $orderId = $order->getId();
        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        );

        $orderRepositoryMock = $this->createMock(EntityRepository::class);
        $orderRepositoryMock
            ->expects($this->once())
            ->method('search')
            ->willReturn($orderSearchResult);

        $orderRepositoryMock
            ->expects($this->once())
            ->method('createVersion')
            ->willReturn('new-order-version-id');

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'language_id' => Defaults::LANGUAGE_SYSTEM,
                    'ids' => $orderId,
                ],
            ]);

        $deliveryNoteRenderer = new DeliveryNoteRenderer(
            $orderRepositoryMock,
            $documentConfigLoaderMock,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $connectionMock,
            static::createStub(DocumentFileRendererRegistry::class),
            new NativeClock()
        );

        $operations = [
            $orderId => new DocumentGenerateOperation(
                $orderId
            ),
        ];

        $result = $deliveryNoteRenderer->render($operations, $context, new DocumentRendererConfig());

        static::assertArrayHasKey($orderId, $result->getSuccess());
        static::assertCount(0, $result->getErrors());
    }

    public function testRenderFailsBeforeReservingANumberForADeliveryOfAnotherOrder(): void
    {
        $order = $this->createOrder();
        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setId(Uuid::randomHex());
        $order->setDeliveries(new OrderDeliveryCollection([$orderDelivery]));

        $numberRangeValueGenerator = $this->createMock(NumberRangeValueGeneratorInterface::class);
        $numberRangeValueGenerator->expects($this->never())->method('getValue');

        $foreignDeliveryId = Uuid::randomHex();
        $operation = new DocumentGenerateOperation($order->getId(), config: ['custom' => ['orderDeliveryId' => $foreignDeliveryId]]);

        $result = $this->createRenderer($order, $numberRangeValueGenerator)
            ->render([$order->getId() => $operation], Context::createDefaultContext(), new DocumentRendererConfig());

        static::assertSame([], $result->getSuccess());
        static::assertEquals(
            DocumentException::orderDeliveryNotFound($foreignDeliveryId, $order->getId()),
            $result->getErrors()[$order->getId()] ?? null,
        );
    }

    public function testRenderFailsForAnOrderDeliveryIdThatIsNoString(): void
    {
        $order = $this->createOrder();
        $operation = new DocumentGenerateOperation($order->getId(), config: ['custom' => ['orderDeliveryId' => ['not', 'an', 'id']]]);

        $result = $this->createRenderer($order, static::createStub(NumberRangeValueGeneratorInterface::class))
            ->render([$order->getId() => $operation], Context::createDefaultContext(), new DocumentRendererConfig());

        static::assertEquals(
            DocumentException::invalidRequestParameter('custom.orderDeliveryId'),
            $result->getErrors()[$order->getId()] ?? null,
        );
    }

    private function createRenderer(OrderEntity $order, NumberRangeValueGeneratorInterface $numberRangeValueGenerator): DeliveryNoteRenderer
    {
        $context = Context::createDefaultContext();

        $orderRepository = static::createStub(EntityRepository::class);
        $orderRepository->method('search')->willReturn(new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            $context
        ));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'language_id' => Defaults::LANGUAGE_SYSTEM,
                'ids' => $order->getId(),
            ],
        ]);

        return new DeliveryNoteRenderer(
            $orderRepository,
            new DocumentConfigLoader(static::createStub(EntityRepository::class), static::createStub(EntityRepository::class)),
            static::createStub(EventDispatcherInterface::class),
            $numberRangeValueGenerator,
            $connection,
            static::createStub(DocumentFileRendererRegistry::class),
            new NativeClock()
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setVersionId(Defaults::LIVE_VERSION);

        $language = new LanguageEntity();
        $language->setId('language-test-id');
        $localeEntity = new LocaleEntity();
        $localeEntity->setCode('en-GB');
        $language->setLocale($localeEntity);

        $order->setLanguage($language);
        $order->setLanguageId('language-test-id');

        return $order;
    }
}
