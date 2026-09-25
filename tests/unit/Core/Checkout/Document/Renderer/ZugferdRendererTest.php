<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopwell\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopwell\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopwell\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopwell\Core\Checkout\Document\Zugferd\ZugferdBuilder;
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
use Shopwell\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdRenderer::class)]
class ZugferdRendererTest extends TestCase
{
    private const ORDER_ID = '0192b305fddb7347be83a311a82f0649';

    public function testSupports(): void
    {
        $renderer = new ZugferdRenderer(
            static::createStub(EntityRepository::class),
            static::createStub(Connection::class),
            static::createStub(ZugferdBuilder::class),
            static::createStub(EventDispatcherInterface::class),
            new DocumentConfigLoader(static::createStub(EntityRepository::class), static::createStub(EntityRepository::class)),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            new NativeClock()
        );

        static::assertSame('zugferd_invoice', $renderer->supports());
    }

    public function testRender(): void
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setSalesChannelId(Uuid::randomHex());

        $orderSearchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext()
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

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['language_id' => Defaults::LANGUAGE_SYSTEM, 'ids' => self::ORDER_ID]]);

        $builder = $this->createMock(ZugferdBuilder::class);
        $builder
            ->expects($this->once())
            ->method('buildDocumentWithType')
            ->willReturn('<?xml version="1.0" encoding="UTF-8"?>');

        $renderer = new ZugferdRenderer(
            $orderRepositoryMock,
            $connection,
            $builder,
            static::createStub(EventDispatcherInterface::class),
            new DocumentConfigLoader(static::createStub(EntityRepository::class), static::createStub(EntityRepository::class)),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            new NativeClock()
        );

        $rendered = $renderer->render(
            [self::ORDER_ID => new DocumentGenerateOperation(self::ORDER_ID)],
            Context::createDefaultContext(),
            new DocumentRendererConfig()
        )->getOrderSuccess(self::ORDER_ID);

        static::assertNotNull($rendered);
        static::assertSame(FileTypes::XML, $rendered->getFileExtension());
        static::assertSame('application/xml', $rendered->getContentType());
        static::assertStringStartsWith('<?xml ', $rendered->getContent());
    }
}
