<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Struct\SmsMessageOptions;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\SmsParamValidator;
use Shopwell\Core\System\Sms\Template\SmsTemplateCollection;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateMappingLocator;
use Shopwell\Core\System\Sms\Template\SmsTemplateRenderer;
use Shopwell\Core\System\Sms\Template\SmsTemplateResolver;
use Shopwell\Core\System\Sms\Util\PhoneNumber;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizerInterface;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTemplateResolver::class)]
class SmsTemplateResolverTest extends TestCase
{
    public function testItRendersTheBodyOfAGatewayThatTakesFreeText(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');
        $gateway = $this->createGateway('twilio');

        $resolver = $this->createResolver([$template], [$gateway], [], ['twilio' => ['templateMode' => false]], [$gateway->id]);

        $message = $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000');

        static::assertSame('+8613800000000', $message->getPhone());
        static::assertSame('Your code is 123456.', $message->getSubject());
        static::assertSame($gateway->id, $message->getTransport());

        $options = $message->getOptions();

        static::assertInstanceOf(SmsMessageOptions::class, $options);
        static::assertSame(SmsTemplateEntity::SCENE_OTP, $options->getScene());
        static::assertSame('customer.otp.login', $options->getTemplateRef());
        static::assertFalse($options->isTemplateBased());
    }

    public function testItSendsTheReportedTemplateOfAChineseProvider(): void
    {
        $template = $this->createTemplate();
        $gateway = $this->createGateway('aliyun');

        $mapping = $this->createMapping($template, 'aliyun', $gateway);
        $mapping->paramsMap = ['code' => 'code', 'ttl' => 'minutes'];

        $resolver = $this->createResolver(
            [$template],
            [$gateway],
            [$mapping],
            ['aliyun' => ['templateMode' => true]],
            [$gateway->id],
        );

        $message = $resolver->resolve('customer.otp.login', ['code' => '123456', 'ttl' => 5], '+8613800000000');

        // the body belongs to the provider, only the name of the template travels with the message
        static::assertSame('customer.otp.login', $message->getSubject());
        static::assertSame($gateway->id, $message->getTransport());

        $options = $message->getOptions();

        static::assertInstanceOf(SmsMessageOptions::class, $options);
        static::assertTrue($options->isTemplateBased());
        static::assertSame('SMS_123456789', $options->getTemplateCode());
        static::assertSame('Shopwell', $options->getSignName());
        static::assertSame(['code' => '123456', 'minutes' => '5'], $options->getParams());
    }

    public function testAReportedTemplateNeedsNoContent(): void
    {
        $template = $this->createTemplate(content: null);
        $gateway = $this->createGateway('aliyun');

        $resolver = $this->createResolver(
            [$template],
            [$gateway],
            [$this->createMapping($template, 'aliyun', $gateway)],
            ['aliyun' => ['templateMode' => true]],
            [$gateway->id],
        );

        static::assertSame(
            $gateway->id,
            $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000')->getTransport()
        );
    }

    public function testItWorksOnTheNumberTheNormalizerAnswers(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');
        $gateway = $this->createGateway('twilio');

        $normalizer = $this->createMock(PhoneNumberNormalizerInterface::class);
        $normalizer->expects($this->once())
            ->method('normalize')
            ->with('138 0000 0000', 'CN')
            ->willReturn(PhoneNumber::fromE164('+8613800000000', 'CN', '86'));

        $resolver = $this->createResolver(
            [$template],
            [$gateway],
            [],
            ['twilio' => []],
            [$gateway->id],
            normalizer: $normalizer,
        );

        // the country the caller knows is handed to the normaliser, which alone decides the e.164
        // form - the resolver never writes a number itself
        $message = $resolver->resolve('customer.otp.login', ['code' => '123456'], '138 0000 0000', null, null, 'CN');

        static::assertSame('+8613800000000', $message->getPhone());
    }

    public function testTheRoutingGetsTheCountryOfTheNormalisedNumber(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');
        $gateway = $this->createGateway('twilio');

        $gatewayResolver = $this->createMock(SmsGatewayResolverInterface::class);
        $gatewayResolver->expects($this->once())
            ->method('resolveGatewayIds')
            ->with(static::anything(), null, 'DE', $template)
            ->willReturn([]);

        $this->createResolver(
            [$template],
            [$gateway],
            [],
            ['twilio' => []],
            gatewayResolver: $gatewayResolver,
            normalizer: $this->normalizerReturning('+4915112345678', 'DE', '49'),
        )->resolve('customer.otp.login', ['code' => '123456'], '+4915112345678', null, null, 'CN');
    }

    public function testACountryTheNumberDoesNotNameReachesTheRoutingAsUnknown(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');
        $gateway = $this->createGateway('twilio');

        $gatewayResolver = $this->createMock(SmsGatewayResolverInterface::class);
        $gatewayResolver->expects($this->once())
            ->method('resolveGatewayIds')
            ->with(static::anything(), null, null, $template)
            ->willReturn([]);

        // the number itself is valid, the dial code data simply does not know its country - the
        // routing has to see that as unknown and not as a domestic number
        $this->createResolver(
            [$template],
            [$gateway],
            [],
            ['twilio' => []],
            gatewayResolver: $gatewayResolver,
            normalizer: $this->normalizerReturning('+998901234567', null, ''),
        )->resolve('customer.otp.login', ['code' => '123456'], '+998901234567');
    }

    public function testItThrowsForATemplateThatDoesNotExist(): void
    {
        $resolver = $this->createResolver([], [], [], []);

        $this->expectExceptionObject(SmsException::templateNotFound('customer.otp.login'));

        $resolver->resolve('customer.otp.login', [], '+8613800000000');
    }

    public function testItThrowsForADisabledTemplate(): void
    {
        $template = $this->createTemplate();
        $template->enabled = false;

        $resolver = $this->createResolver([$template], [], [], []);

        $this->expectExceptionObject(SmsException::templateDisabled('customer.otp.login'));

        $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000');
    }

    public function testItThrowsForAParameterOutsideTheContractOfTheTemplate(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');

        $resolver = $this->createResolver([$template], [], [], []);

        $this->expectExceptionObject(SmsException::invalidTemplateParams(
            'customer.otp.login',
            ['"code" does not match ^\d{6}$']
        ));

        $resolver->resolve('customer.otp.login', ['code' => 'abc'], '+8613800000000');
    }

    public function testItThrowsForTheNumberBeforeAProviderIsCalled(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');

        $resolver = $this->createResolver([$template], [], [], []);

        $this->expectExceptionObject(SmsException::invalidPhoneNumber('nope'));

        $resolver->resolve('customer.otp.login', ['code' => '123456'], 'nope');
    }

    public function testItThrowsWhenAReportedTemplateHasNoMappingForItsGateway(): void
    {
        $template = $this->createTemplate();
        $gateway = $this->createGateway('aliyun');

        $resolver = $this->createResolver(
            [$template],
            [$gateway],
            [],
            ['aliyun' => ['templateMode' => true]],
            [$gateway->id],
        );

        $this->expectExceptionObject(SmsException::templateNotConfigured('customer.otp.login', 'aliyun'));

        $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000');
    }

    public function testTheMappingOfTheAccountWinsOverTheSharedOne(): void
    {
        $template = $this->createTemplate();
        $gateway = $this->createGateway('aliyun');

        $shared = $this->createMapping($template, 'aliyun');
        $shared->templateCode = 'SMS_SHARED';

        $account = $this->createMapping($template, 'aliyun', $gateway);
        $account->templateCode = 'SMS_ACCOUNT';

        $resolver = $this->createResolver(
            [$template],
            [$gateway],
            [$shared, $account],
            ['aliyun' => ['templateMode' => true]],
            [$gateway->id],
        );

        $message = $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000');
        $options = $message->getOptions();

        static::assertInstanceOf(SmsMessageOptions::class, $options);
        static::assertSame('SMS_ACCOUNT', $options->getTemplateCode());
    }

    public function testItFallsBackToTheMappingThatHoldsForEveryAccount(): void
    {
        $template = $this->createTemplate();
        $gateway = $this->createGateway('aliyun');

        $resolver = $this->createResolver(
            [$template],
            [$gateway],
            [$this->createMapping($template, 'aliyun')],
            ['aliyun' => ['templateMode' => true]],
            [$gateway->id],
        );

        $options = $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000')->getOptions();

        static::assertInstanceOf(SmsMessageOptions::class, $options);
        static::assertSame('SMS_123456789', $options->getTemplateCode());
    }

    public function testItFallsBackToTheNullGatewayWhenTheRoutingFoundNothing(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');

        $resolver = $this->createResolver([$template], [], [], []);

        $message = $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000');

        // the message is still built and recorded, it simply does not leave the platform
        static::assertSame(SmsGatewayResolverInterface::GATEWAY_NULL, $message->getTransport());
        static::assertSame('Your code is 123456.', $message->getSubject());

        $options = $message->getOptions();

        static::assertInstanceOf(SmsMessageOptions::class, $options);
        // the record of the attempt has to say why nothing took the message
        static::assertSame(SmsException::NO_USABLE_GATEWAY, $options->getUndeliverableReason());
    }

    public function testItNamesTheAuditThatKeepsTheMessageFromGoingOut(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');
        $gateway = $this->createGateway('aliyun');

        $mapping = $this->createMapping($template, 'aliyun', $gateway);
        $mapping->auditStatus = TemplateApprovalStatus::PENDING;

        // the routing drops the only gateway because the template of the shop is reported there and
        // not approved yet, and an empty candidate list is the same answer it gives for a shop
        // without a usable gateway - the record has to name the audit instead
        $resolver = $this->createResolver([$template], [$gateway], [$mapping], ['aliyun' => ['templateMode' => true]]);

        $message = $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000');

        static::assertSame(SmsGatewayResolverInterface::GATEWAY_NULL, $message->getTransport());

        $options = $message->getOptions();

        static::assertInstanceOf(SmsMessageOptions::class, $options);
        static::assertSame(SmsException::TEMPLATE_NOT_APPROVED, $options->getUndeliverableReason());
        // the state and the provider that is still auditing are part of the sentence
        static::assertSame(
            SmsException::templateNotApproved('customer.otp.login', 'aliyun', TemplateApprovalStatus::PENDING)->getMessage(),
            $options->getUndeliverableMessage()
        );
    }

    public function testAnApprovedTemplateWithoutAUsableGatewayStaysANoGatewayCase(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');
        $gateway = $this->createGateway('aliyun');

        // every row of the template is approved, so the audit is not what kept the routing empty
        $mapping = $this->createMapping($template, 'aliyun', $gateway);

        $resolver = $this->createResolver([$template], [$gateway], [$mapping], ['aliyun' => ['templateMode' => true]]);

        $options = $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000')->getOptions();

        static::assertInstanceOf(SmsMessageOptions::class, $options);
        static::assertSame(SmsException::NO_USABLE_GATEWAY, $options->getUndeliverableReason());
        static::assertNull($options->getUndeliverableMessage());
    }

    public function testItThrowsForAReportedTemplateWithoutACode(): void
    {
        $template = $this->createTemplate();
        $gateway = $this->createGateway('aliyun');

        $mapping = $this->createMapping($template, 'aliyun', $gateway);
        $mapping->templateCode = null;

        $resolver = $this->createResolver(
            [$template],
            [$gateway],
            [$mapping],
            ['aliyun' => ['templateMode' => true]],
            [$gateway->id],
        );

        $this->expectExceptionObject(SmsException::templateNotConfigured('customer.otp.login', 'aliyun'));

        $resolver->resolve('customer.otp.login', ['code' => '123456'], '+8613800000000');
    }

    /**
     * @param list<SmsTemplateEntity> $templates
     * @param list<SmsGatewayEntity> $gateways
     * @param list<SmsTemplateMappingEntity> $mappings
     * @param array<string, array{regions?: list<string>, templateMode?: bool}> $factories
     * @param list<string> $resolvedGatewayIds
     */
    private function createResolver(
        array $templates,
        array $gateways,
        array $mappings,
        array $factories,
        array $resolvedGatewayIds = [],
        ?SmsGatewayResolverInterface $gatewayResolver = null,
        ?PhoneNumberNormalizerInterface $normalizer = null,
    ): SmsTemplateResolver {
        $mappingRepository = StaticEntityRepository::of(
            SmsTemplateMappingCollection::class,
            [new SmsTemplateMappingCollection($mappings)]
        );

        if ($gatewayResolver === null) {
            $stub = static::createStub(SmsGatewayResolverInterface::class);
            $stub->method('resolveGatewayIds')->willReturn($resolvedGatewayIds);

            $gatewayResolver = $stub;
        }

        return new SmsTemplateResolver(
            StaticEntityRepository::of(SmsTemplateCollection::class, [new SmsTemplateCollection($templates)]),
            new SmsTemplateMappingLocator($mappingRepository),
            StaticEntityRepository::of(SmsGatewayCollection::class, [new SmsGatewayCollection($gateways)]),
            $this->createRegistry($factories),
            $gatewayResolver,
            new SmsParamValidator(),
            new SmsTemplateRenderer(),
            $normalizer ?? $this->normalizerKeepingTheNumberAsWritten(),
        );
    }

    /**
     * The normaliser of the cases that do not care about it: it keeps the number as written and
     * places it nowhere, so the routing sees the unknown country of a number nobody named.
     */
    private function normalizerKeepingTheNumberAsWritten(): PhoneNumberNormalizerInterface
    {
        $normalizer = static::createStub(PhoneNumberNormalizerInterface::class);
        $normalizer->method('normalize')->willReturnCallback(
            static fn (string $phone): PhoneNumber => PhoneNumber::fromE164($phone, null, '')
        );

        return $normalizer;
    }

    /**
     * The normaliser of the cases that care about the country the number carries.
     */
    private function normalizerReturning(string $e164, ?string $countryIso, string $dialCode): PhoneNumberNormalizerInterface
    {
        $normalizer = static::createStub(PhoneNumberNormalizerInterface::class);
        $normalizer->method('normalize')->willReturn(PhoneNumber::fromE164($e164, $countryIso, $dialCode));

        return $normalizer;
    }

    /**
     * @param array<string, array{regions?: list<string>, templateMode?: bool}> $factories
     */
    private function createRegistry(array $factories): SmsGatewayRegistry
    {
        $stubs = [];

        foreach ($factories as $technicalName => $config) {
            $factory = static::createStub(SmsGatewayTransportFactoryInterface::class);
            $factory->method('getTechnicalName')->willReturn($technicalName);
            $factory->method('getSupportedRegions')->willReturn($config['regions'] ?? ['GLOBAL']);
            $factory->method('supportsTemplateMode')->willReturn($config['templateMode'] ?? false);
            $factory->method('getLabelSnippet')->willReturn('sms.gateway.' . $technicalName);
            $factory->method('getCredentialDefinitions')->willReturn([]);

            $stubs[] = $factory;
        }

        return new SmsGatewayRegistry($stubs);
    }

    private function createTemplate(?string $content = null): SmsTemplateEntity
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
        $gateway->credentials = ['accessKeyId' => 'key'];
        $gateway->options = [];

        return $gateway;
    }

    private function createMapping(
        SmsTemplateEntity $template,
        string $technicalName,
        ?SmsGatewayEntity $gateway = null,
    ): SmsTemplateMappingEntity {
        $mapping = new SmsTemplateMappingEntity();
        $mapping->id = Uuid::randomHex();
        $mapping->setUniqueIdentifier($mapping->id);
        $mapping->templateId = $template->id;
        $mapping->technicalName = $technicalName;
        $mapping->auditStatus = TemplateApprovalStatus::APPROVED;
        $mapping->templateCode = 'SMS_123456789';
        $mapping->signName = 'Shopwell';
        $mapping->gatewayId = $gateway?->id;

        return $mapping;
    }
}
