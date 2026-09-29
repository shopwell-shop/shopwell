<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer;
use Shopwell\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexingMessage;
use Shopwell\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerFlowEventsSubscriber;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelException;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CustomerFlowEventsSubscriber::class)]
class CustomerFlowEventsSubscriberTest extends TestCase
{
    private Stub&EventDispatcherInterface $dispatcher;

    private Stub&SalesChannelContextRestorer $restorer;

    private Stub&CustomerIndexer $customerIndexer;

    private IdsCollection $ids;

    private CustomerFlowEventsSubscriber $customerFlowEventsSubscriber;

    private Connection&Stub $connection;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->dispatcher = static::createStub(EventDispatcherInterface::class);
        $this->restorer = static::createStub(SalesChannelContextRestorer::class);
        $this->customerIndexer = static::createStub(CustomerIndexer::class);
        $this->connection = static::createStub(Connection::class);

        $this->customerFlowEventsSubscriber = $this->buildSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ], $this->customerFlowEventsSubscriber->getSubscribedEvents());
    }

    public function testOnCustomerWrittenWithInstanceOfSaleChannelApi(): void
    {
        $context = Context::createDefaultContext(new SalesChannelApiSource(Defaults::SALES_CHANNEL_TYPE_API));

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $this->customerFlowEventsSubscriber->onCustomerWritten($event);
    }

    public function testOnCustomerWrittenWithInstanceOfAdminApiButGettingErrorProvidedLanguageNotAvailable(): void
    {
        $this->expectException(SalesChannelException::class);

        $context = Context::createDefaultContext(new AdminApiSource(Defaults::SALES_CHANNEL_TYPE_API));

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->atLeast(1))
            ->method('getContext')
            ->willReturn($context);

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $customerIndexer = $this->createMock(CustomerIndexer::class);
        $customerIndexer->expects($this->never())
            ->method('handle');

        $restorer = $this->createMock(SalesChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByCustomer')
            ->willThrowException(SalesChannelException::providedLanguageNotAvailable('zh-CN', ['en-GB']));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('delete');

        $this->buildSubscriber($dispatcher, $restorer, $customerIndexer, $connection)->onCustomerWritten($event);
    }

    public function testOnCustomerWrittenWithInstanceOfAdminApiButGettingOtherError(): void
    {
        $this->expectException(SalesChannelException::class);

        $context = Context::createDefaultContext(new AdminApiSource(Defaults::SALES_CHANNEL_TYPE_API));

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->atLeast(1))
            ->method('getContext')
            ->willReturn($context);

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $customerIndexer = $this->createMock(CustomerIndexer::class);
        $customerIndexer->expects($this->never())
            ->method('handle');

        $restorer = $this->createMock(SalesChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByCustomer')
            ->willThrowException(SalesChannelException::salesChannelNotFound('sales-channel-id'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('delete');

        $this->buildSubscriber($dispatcher, $restorer, $customerIndexer, $connection)->onCustomerWritten($event);
    }

    public function testOnCustomerCreatedWithoutCustomerInContext(): void
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->buildSubscriber($dispatcher)->onCustomerWritten($event);
    }

    public function testOnCustomerCreatedWithCustomer(): void
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('customerId'),
            ],
        ];

        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $customerIndexer = $this->createMock(CustomerIndexer::class);
        $customerIndexer->expects($this->once())
            ->method('handle')
            ->with(new CustomerIndexingMessage([$this->ids->get('customerId')]));

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $restorer = $this->createMock(SalesChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByCustomer')
            ->willReturn($salesChannelContext);

        $customerCreated = new CustomerRegisterEvent(
            $salesChannelContext,
            $customer
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($customerCreated);

        $this->buildSubscriber($dispatcher, $restorer, $customerIndexer)->onCustomerWritten($event);
    }

    private function buildSubscriber(
        ?EventDispatcherInterface $dispatcher = null,
        ?SalesChannelContextRestorer $restorer = null,
        ?CustomerIndexer $customerIndexer = null,
        ?Connection $connection = null,
    ): CustomerFlowEventsSubscriber {
        return new CustomerFlowEventsSubscriber(
            $dispatcher ?? $this->dispatcher,
            $restorer ?? $this->restorer,
            $customerIndexer ?? $this->customerIndexer,
            $connection ?? $this->connection,
        );
    }
}
