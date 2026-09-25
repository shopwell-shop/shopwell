<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ProductExport\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopwell\Core\Content\ProductExport\ProductExportEntity;
use Shopwell\Core\Content\ProductExport\Provider\AbstractAgenticCommerceProductExportProvider;
use Shopwell\Core\Content\ProductExport\Provider\AgenticCommerceProductExportProviderRegistry;
use Shopwell\Core\Content\ProductExport\Subscriber\AgenticCommerceProductExportProviderContextSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AgenticCommerceProductExportProviderContextSubscriber::class)]
class AgenticCommerceProductExportProviderContextSubscriberTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [ProductExportRenderBodyContextEvent::class => 'extendBodyContext'],
            AgenticCommerceProductExportProviderContextSubscriber::getSubscribedEvents()
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendBodyContextAddsProviderSpecificContext(): void
    {
        $subscriber = new AgenticCommerceProductExportProviderContextSubscriber(
            new AgenticCommerceProductExportProviderRegistry([
                $this->createProvider(),
            ])
        );

        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setSalesChannelId(Uuid::randomHex());
        $productExport->setProvider('open-ai');

        $salesChannelContext = $this->createSalesChannelContext();

        $event = new ProductExportRenderBodyContextEvent([
            'productExport' => $productExport,
            'context' => $salesChannelContext,
        ]);

        $subscriber->extendBodyContext($event);

        static::assertInstanceOf(ArrayStruct::class, $event->getContext()['provider']);
        static::assertSame('open-ai', $event->getContext()['provider']->get('name'));
        static::assertSame($productExport, $event->getContext()['productExport']);
        static::assertSame($salesChannelContext, $event->getContext()['context']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendBodyContextDoesNothingWithoutProviderKey(): void
    {
        $subscriber = new AgenticCommerceProductExportProviderContextSubscriber(
            new AgenticCommerceProductExportProviderRegistry([
                $this->createProvider(),
            ])
        );

        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());

        $salesChannelContext = $this->createSalesChannelContext();

        $event = new ProductExportRenderBodyContextEvent([
            'productExport' => $productExport,
            'context' => $salesChannelContext,
        ]);

        $subscriber->extendBodyContext($event);

        static::assertArrayNotHasKey('provider', $event->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendBodyContextDoesNothingWhenContextIsIncomplete(): void
    {
        $subscriber = new AgenticCommerceProductExportProviderContextSubscriber(
            new AgenticCommerceProductExportProviderRegistry([
                $this->createProvider(),
            ])
        );

        $event = new ProductExportRenderBodyContextEvent([
            'productExport' => new ProductExportEntity(),
        ]);

        $subscriber->extendBodyContext($event);

        static::assertSame(['productExport' => $event->getContext()['productExport']], $event->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendBodyContextDoesNothingWhenProviderIsNotRegistered(): void
    {
        $subscriber = new AgenticCommerceProductExportProviderContextSubscriber(
            new AgenticCommerceProductExportProviderRegistry([])
        );

        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setSalesChannelId(Uuid::randomHex());
        $productExport->setProvider('open-ai');

        $salesChannelContext = $this->createSalesChannelContext();

        $event = new ProductExportRenderBodyContextEvent([
            'productExport' => $productExport,
            'context' => $salesChannelContext,
        ]);

        $subscriber->extendBodyContext($event);

        static::assertArrayNotHasKey('provider', $event->getContext());
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        return Generator::generateSalesChannelContext(
            baseContext: Context::createDefaultContext(),
            salesChannel: $salesChannel
        );
    }

    private function createProvider(): AbstractAgenticCommerceProductExportProvider
    {
        return new class('open-ai') extends AbstractAgenticCommerceProductExportProvider {
            public function __construct(private readonly string $technicalName)
            {
            }

            public function getTechnicalName(): string
            {
                return $this->technicalName;
            }

            protected function buildProviderContext(
                ProductExportEntity $productExport,
                SalesChannelContext $salesChannelContext,
            ): array {
                return [];
            }
        };
    }
}
