<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Shopwell\Core\System\Sms\Message\SendLowPrioritySmsMessage;
use Shopwell\Core\System\Sms\Message\SendSmsMessage;
use Shopwell\Core\System\Sms\Service\SmsSender;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Struct\SmsMessageOptions;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\SmsParamValidator;
use Shopwell\Core\System\Sms\Template\SmsTemplateCollection;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateRenderer;
use Shopwell\Core\System\Sms\Template\SmsTemplateResolver;
use Shopwell\Core\System\Sms\Transport\SmsTransportLoader;
use Shopwell\Core\System\Sms\Transport\SmsTransports;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsSender::class)]
class SmsSenderTest extends TestCase
{
    public function testAnOtpIsSentRightAway(): void
    {
        $sender = $this->createSender(resolvedGatewayIds: ['gateway-id'], sendsOver: 'gateway-id');

        $message = $this->createMessage(SmsTemplateEntity::SCENE_OTP);

        static::assertInstanceOf(SentMessage::class, $sender->send($message, Context::createDefaultContext()));

        // the gateway was resolved and travels with the message for the record of the delivery
        static::assertSame('gateway-id', $message->getTransport());
    }

    public function testItNormalisesTheNumberOfTheRecipient(): void
    {
        $sender = $this->createSender(resolvedGatewayIds: ['gateway-id'], sendsOver: 'gateway-id');

        $message = $this->createMessage(SmsTemplateEntity::SCENE_OTP, phone: '138 0000 0000');
        $sender->send($message, Context::createDefaultContext());

        static::assertSame('+8613800000000', $message->getPhone());
    }

    public function testItFallsBackToTheNullGatewayWhenNothingMatched(): void
    {
        $sender = $this->createSender(sendsOver: SmsGatewayResolverInterface::GATEWAY_NULL);

        $message = $this->createMessage(SmsTemplateEntity::SCENE_OTP);

        static::assertInstanceOf(SentMessage::class, $sender->send($message, Context::createDefaultContext()));
        static::assertSame(SmsGatewayResolverInterface::GATEWAY_NULL, $message->getTransport());
    }

    public function testItKeepsTheGatewayTheMessageCarries(): void
    {
        // the routing would pick another one, the message must win
        $sender = $this->createSender(resolvedGatewayIds: ['other-gateway-id'], sendsOver: 'carried-gateway-id');

        $message = $this->createMessage(SmsTemplateEntity::SCENE_OTP, transport: 'carried-gateway-id');

        static::assertInstanceOf(SentMessage::class, $sender->send($message, Context::createDefaultContext()));
        static::assertSame('carried-gateway-id', $message->getTransport());
    }

    public function testATransactionMessageIsQueued(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(SendSmsMessage::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $sender = $this->createSender(bus: $bus);

        static::assertNull($sender->send($this->createMessage(SmsTemplateEntity::SCENE_TRANSACTION), Context::createDefaultContext()));
    }

    public function testAMarketingMessageTakesTheLowPriorityQueue(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(SendLowPrioritySmsMessage::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $sender = $this->createSender(bus: $bus);

        static::assertNull($sender->send($this->createMessage(SmsTemplateEntity::SCENE_MARKETING), Context::createDefaultContext()));
    }

    public function testSendTemplateResolvesAndSendsRightAway(): void
    {
        $template = $this->createTemplate('Your code is {{ code }}.');
        $gateway = $this->createGateway('twilio');

        $sender = $this->createSender(
            templates: [$template],
            gateways: [$gateway],
            resolvedGatewayIds: [$gateway->id],
            sendsOver: $gateway->id,
        );

        static::assertInstanceOf(
            SentMessage::class,
            $sender->sendTemplate('customer.otp.login', ['code' => '123456'], '+8613800000000')
        );
    }

    public function testSendTemplateRejectsAParameterOutsideTheContract(): void
    {
        $template = $this->createTemplate('Your code is {{ code }}.');
        $gateway = $this->createGateway('twilio');

        $sender = $this->createSender(
            templates: [$template],
            gateways: [$gateway],
            resolvedGatewayIds: [$gateway->id],
        );

        $this->expectExceptionObject(SmsException::invalidTemplateParams(
            'customer.otp.login',
            ['"code" does not match ^\d{6}$']
        ));

        $sender->sendTemplate('customer.otp.login', ['code' => 'abc'], '+8613800000000');
    }

    /**
     * @param list<SmsTemplateEntity> $templates
     * @param list<SmsGatewayEntity> $gateways
     * @param list<string> $resolvedGatewayIds
     * @param string|null $sendsOver the gateway the message has to leave the platform over, null asserts
     *                               that the test sends nothing at all
     */
    private function createSender(
        ?MessageBusInterface $bus = null,
        array $templates = [],
        array $gateways = [],
        array $resolvedGatewayIds = [],
        ?string $sendsOver = null,
    ): SmsSender {
        $gatewayResolver = static::createStub(SmsGatewayResolverInterface::class);
        $gatewayResolver->method('resolveGatewayIds')->willReturn($resolvedGatewayIds);

        $transportLoader = $this->createMock(SmsTransportLoader::class);

        if ($sendsOver === null) {
            $transportLoader->expects($this->never())->method('fromGatewayId');
        } else {
            $transport = static::createStub(TransportInterface::class);
            $transport->method('send')->willReturnCallback(
                static fn (MessageInterface $message): SentMessage => new SentMessage($message, 'sms')
            );

            $transportLoader->expects($this->once())
                ->method('fromGatewayId')
                ->with($sendsOver)
                ->willReturn($transport);
        }

        $factories = [];
        foreach ($gateways as $gateway) {
            $factory = static::createStub(SmsGatewayTransportFactoryInterface::class);
            $factory->method('getTechnicalName')->willReturn($gateway->technicalName);
            $factory->method('getSupportedRegions')->willReturn(['GLOBAL']);
            $factory->method('supportsTemplateMode')->willReturn(false);
            $factory->method('getLabelSnippet')->willReturn('sms.gateway.' . $gateway->technicalName);
            $factory->method('getCredentialDefinitions')->willReturn([]);

            $factories[] = $factory;
        }

        $templateResolver = new SmsTemplateResolver(
            StaticEntityRepository::of(SmsTemplateCollection::class, [new SmsTemplateCollection($templates)]),
            StaticEntityRepository::of(SmsTemplateMappingCollection::class, [new SmsTemplateMappingCollection()]),
            StaticEntityRepository::of(SmsGatewayCollection::class, [new SmsGatewayCollection($gateways)]),
            new SmsGatewayRegistry($factories),
            $gatewayResolver,
            new SmsParamValidator(),
            new SmsTemplateRenderer(),
            new PhoneNumberNormalizer(new StaticSystemConfigService()),
        );

        return new SmsSender(
            new SmsTransports($transportLoader),
            $templateResolver,
            $gatewayResolver,
            new PhoneNumberNormalizer(new StaticSystemConfigService()),
            $bus ?? static::createStub(MessageBusInterface::class),
        );
    }

    private function createMessage(
        string $scene,
        string $phone = '+8613800000000',
        ?string $transport = null,
    ): SmsMessage {
        $message = new SmsMessage($phone, 'hello', '', new SmsMessageOptions(scene: $scene));

        if ($transport !== null) {
            $message->transport($transport);
        }

        return $message;
    }

    private function createTemplate(string $content): SmsTemplateEntity
    {
        $template = new SmsTemplateEntity();
        $template->id = Uuid::randomHex();
        $template->setUniqueIdentifier($template->id);
        $template->technicalName = 'customer.otp.login';
        $template->scene = SmsTemplateEntity::SCENE_OTP;
        $template->content = $content;
        $template->paramsSchema = [['name' => 'code', 'required' => true, 'pattern' => '^\d{6}$']];
        $template->enabled = true;

        return $template;
    }

    private function createGateway(string $technicalName): SmsGatewayEntity
    {
        $gateway = new SmsGatewayEntity();
        $gateway->id = Uuid::randomHex();
        $gateway->setUniqueIdentifier($gateway->id);
        $gateway->technicalName = $technicalName;
        $gateway->enabled = true;
        $gateway->strategy = SmsGatewayEntity::STRATEGY_EXCLUSIVE;
        $gateway->credentials = [];
        $gateway->options = [];

        return $gateway;
    }
}
