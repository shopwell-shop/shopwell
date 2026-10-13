<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Subscriber;

use Doctrine\DBAL\Connection;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsDeliveryCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsGatewayCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsGatewayEntity;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateEntity;
use Shopwell\Core\Framework\Sms\Event\SmsFailedEvent;
use Shopwell\Core\Framework\Sms\Event\SmsSentEvent;
use Shopwell\Core\Framework\Sms\Struct\SmsMessageOptions;
use Shopwell\Core\Framework\Sms\Subscriber\SmsDeliverySubscriber;
use Shopwell\Core\Framework\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Notifier\Event\FailedMessageEvent;
use Symfony\Component\Notifier\Event\SentMessageEvent;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsDeliverySubscriber::class)]
class SmsDeliverySubscriberTest extends TestCase
{
    public function testItRecordsAnAcceptedMessage(): void
    {
        $gateway = $this->createGateway('aliyun');
        $template = $this->createTemplate();
        $deliveryRepository = $this->createDeliveryRepository();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('insert');

        $dispatcher = new EventDispatcher();
        $events = [];
        $dispatcher->addListener(SmsSentEvent::class, static function (SmsSentEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $subscriber = $this->createSubscriber(
            $deliveryRepository,
            [$gateway],
            [$template],
            $connection,
            $dispatcher,
        );

        $message = $this->createMessage($gateway, 'customer.otp.login');
        $sentMessage = new SentMessage($message, 'aliyun');
        $sentMessage->setMessageId('provider-message-id');

        $subscriber->onSentMessage(new SentMessageEvent($sentMessage));

        static::assertCount(1, $deliveryRepository->creates);

        $record = $deliveryRepository->creates[0][0];

        static::assertSame($gateway->id, $record['gatewayId']);
        static::assertSame('aliyun', $record['technicalName']);
        static::assertSame('+86138****0000', $record['phoneMasked']);
        static::assertSame(Hasher::hash('+8613800000000'), $record['phoneHash']);
        static::assertSame('CN', $record['countryCode']);
        static::assertSame(SmsTemplateEntity::SCENE_OTP, $record['scene']);
        static::assertSame($template->id, $record['templateId']);
        static::assertSame('customer.otp.login', $record['templateRef']);
        static::assertSame(Hasher::hash('Your code is 123456.'), $record['contentHash']);
        static::assertSame('sent', $record['status']);
        static::assertSame('provider-message-id', $record['providerMessageId']);
        static::assertSame(1, $record['segments']);
        static::assertInstanceOf(\DateTimeInterface::class, $record['sentAt']);
        static::assertNull($record['failedAt']);

        static::assertCount(1, $events);
        static::assertSame('aliyun', $events[0]->getTechnicalName());
        static::assertSame($gateway->id, $events[0]->getGatewayId());
        static::assertSame('provider-message-id', $events[0]->getProviderMessageId());
    }

    public function testItNeverStoresTheBodyOfAMessage(): void
    {
        $gateway = $this->createGateway('aliyun');
        $deliveryRepository = $this->createDeliveryRepository();

        $subscriber = $this->createSubscriber($deliveryRepository, [$gateway], []);

        $subscriber->onSentMessage(new SentMessageEvent(new SentMessage($this->createMessage($gateway), 'aliyun')));

        $record = $deliveryRepository->creates[0][0];

        static::assertArrayNotHasKey('content', $record);
        static::assertArrayNotHasKey('message', $record);
        static::assertNotContains('Your code is 123456.', $record);
        static::assertSame(Hasher::hash('Your code is 123456.'), $record['contentHash']);
    }

    public function testItRecordsARefusedMessageAndWritesALogEntry(): void
    {
        $gateway = $this->createGateway('aliyun');
        $template = $this->createTemplate();
        $deliveryRepository = $this->createDeliveryRepository();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with('log_entry', static::callback(static function (array $row): bool {
                static::assertSame('sms.message.failed', $row['message']);
                static::assertSame(Level::Error->value, $row['level']);
                static::assertSame('sms', $row['channel']);
                static::assertNotNull($row['context']);
                static::assertNotNull($row['extra']);
                static::assertStringContainsString('the provider refused it', (string) $row['context']);

                return true;
            }));

        $dispatcher = new EventDispatcher();
        $events = [];
        $dispatcher->addListener(SmsFailedEvent::class, static function (SmsFailedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $subscriber = $this->createSubscriber(
            $deliveryRepository,
            [$gateway],
            [$template],
            $connection,
            $dispatcher,
        );

        $message = $this->createMessage($gateway, 'customer.otp.login');
        $subscriber->onFailedMessage(new FailedMessageEvent($message, new \RuntimeException('the provider refused it', 42)));

        $record = $deliveryRepository->creates[0][0];

        static::assertSame('failed', $record['status']);
        static::assertSame('42', $record['errorCode']);
        static::assertSame('the provider refused it', $record['errorMessage']);
        static::assertNull($record['sentAt']);
        static::assertInstanceOf(\DateTimeInterface::class, $record['failedAt']);

        static::assertCount(1, $events);
        static::assertSame('aliyun', $events[0]->getTechnicalName());
    }

    public function testItRecordsAMessageWithoutAGatewayOverTheNullGateway(): void
    {
        $deliveryRepository = $this->createDeliveryRepository();

        $subscriber = $this->createSubscriber($deliveryRepository, [], []);

        $message = new SmsMessage('+8613800000000', 'hello');
        $subscriber->onSentMessage(new SentMessageEvent(new SentMessage($message, 'null')));

        $record = $deliveryRepository->creates[0][0];

        static::assertNull($record['gatewayId']);
        static::assertSame('null', $record['technicalName']);
        static::assertSame('sent', $record['status']);
    }

    public function testItRecordsAVendorMessageThatCarriesNoOptions(): void
    {
        $gateway = $this->createGateway('twilio');
        $deliveryRepository = $this->createDeliveryRepository();

        $subscriber = $this->createSubscriber($deliveryRepository, [$gateway], []);

        $message = (new SmsMessage('+8613800000000', 'the body'))->transport($gateway->id);
        $subscriber->onSentMessage(new SentMessageEvent(new SentMessage($message, 'twilio')));

        $record = $deliveryRepository->creates[0][0];

        static::assertNull($record['scene']);
        static::assertNull($record['templateId']);
        static::assertNull($record['templateRef']);
        static::assertSame(Hasher::hash('the body'), $record['contentHash']);
    }

    public function testItIgnoresAMessageOfAnotherChannel(): void
    {
        $deliveryRepository = $this->createDeliveryRepository();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('insert');

        $subscriber = $this->createSubscriber($deliveryRepository, [], [], $connection);

        $subscriber->onSentMessage(new SentMessageEvent(new SentMessage(new ChatMessage('hello'), 'chat')));
        $subscriber->onFailedMessage(new FailedMessageEvent(new ChatMessage('hello'), new \RuntimeException('nope')));

        static::assertSame([], $deliveryRepository->creates);
    }

    public function testItLooksUpTheTemplateOnlyOncePerMessageOfTheSameTemplate(): void
    {
        $gateway = $this->createGateway('aliyun');
        $template = $this->createTemplate();
        $deliveryRepository = $this->createDeliveryRepository();

        // the template repository holds a single answer on purpose - a second lookup would fail
        $subscriber = $this->createSubscriber($deliveryRepository, [$gateway], [$template]);

        $message = $this->createMessage($gateway, 'customer.otp.login');

        $subscriber->onSentMessage(new SentMessageEvent(new SentMessage($message, 'aliyun')));
        $subscriber->onSentMessage(new SentMessageEvent(new SentMessage($message, 'aliyun')));

        static::assertCount(2, $deliveryRepository->creates);
        static::assertSame($template->id, $deliveryRepository->creates[1][0]['templateId']);
    }

    /**
     * @param StaticEntityRepository<SmsDeliveryCollection> $deliveryRepository
     * @param list<SmsGatewayEntity> $gateways
     * @param list<SmsTemplateEntity> $templates
     */
    private function createSubscriber(
        StaticEntityRepository $deliveryRepository,
        array $gateways = [],
        array $templates = [],
        ?Connection $connection = null,
        ?EventDispatcher $dispatcher = null,
    ): SmsDeliverySubscriber {
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-14T10:00:00+00:00'));

        // the stub consumes one configured answer per lookup and a test records more than one message
        $gatewayAnswer = static fn (Criteria $criteria): SmsGatewayCollection => new SmsGatewayCollection($gateways);

        return new SmsDeliverySubscriber(
            $deliveryRepository,
            StaticEntityRepository::of(SmsGatewayCollection::class, array_fill(0, 4, $gatewayAnswer)),
            StaticEntityRepository::of(SmsTemplateCollection::class, [new SmsTemplateCollection($templates)]),
            new PhoneNumberNormalizer(new StaticSystemConfigService()),
            $connection ?? $this->createMock(Connection::class),
            $clock,
            $dispatcher ?? new EventDispatcher(),
        );
    }

    /**
     * @return StaticEntityRepository<SmsDeliveryCollection>
     */
    private function createDeliveryRepository(): StaticEntityRepository
    {
        return StaticEntityRepository::of(SmsDeliveryCollection::class, []);
    }

    private function createMessage(SmsGatewayEntity $gateway, ?string $templateRef = null): SmsMessage
    {
        $options = new SmsMessageOptions(
            scene: SmsTemplateEntity::SCENE_OTP,
            templateRef: $templateRef,
        );

        return (new SmsMessage('+8613800000000', 'Your code is 123456.', '', $options))->transport($gateway->id);
    }

    private function createGateway(string $technicalName): SmsGatewayEntity
    {
        $gateway = new SmsGatewayEntity();
        $gateway->id = Uuid::randomHex();
        $gateway->setUniqueIdentifier($gateway->id);
        $gateway->technicalName = $technicalName;
        $gateway->enabled = true;
        $gateway->strategy = SmsGatewayEntity::STRATEGY_EXCLUSIVE;

        return $gateway;
    }

    private function createTemplate(): SmsTemplateEntity
    {
        $template = new SmsTemplateEntity();
        $template->id = Uuid::randomHex();
        $template->setUniqueIdentifier($template->id);
        $template->technicalName = 'customer.otp.login';
        $template->scene = SmsTemplateEntity::SCENE_OTP;
        $template->enabled = true;

        return $template;
    }
}
