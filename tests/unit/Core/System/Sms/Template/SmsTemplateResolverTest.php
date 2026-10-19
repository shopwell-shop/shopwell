<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Struct\SmsMessageOptions;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateBodyReader;
use Shopwell\Core\System\Sms\Template\SmsParamValidator;
use Shopwell\Core\System\Sms\Template\SmsTemplateCollection;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateRenderer;
use Shopwell\Core\System\Sms\Template\SmsTemplateResolver;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

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

    public function testItNormalisesTheNumberOfTheRecipient(): void
    {
        $template = $this->createTemplate(content: 'Your code is {{ code }}.');
        $gateway = $this->createGateway('twilio');

        $resolver = $this->createResolver([$template], [$gateway], [], ['twilio' => []], [$gateway->id]);

        $message = $resolver->resolve('customer.otp.login', ['code' => '123456'], '138 0000 0000');

        static::assertSame('+8613800000000', $message->getPhone());
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
    ): SmsTemplateResolver {
        $gatewayResolver = static::createStub(SmsGatewayResolverInterface::class);
        $gatewayResolver->method('resolveGatewayIds')->willReturn($resolvedGatewayIds);

        return new SmsTemplateResolver(
            StaticEntityRepository::of(SmsTemplateCollection::class, [new SmsTemplateCollection($templates)]),
            StaticEntityRepository::of(SmsTemplateMappingCollection::class, [new SmsTemplateMappingCollection($mappings)]),
            StaticEntityRepository::of(SmsGatewayCollection::class, [new SmsGatewayCollection($gateways)]),
            $this->createRegistry($factories),
            $gatewayResolver,
            new SmsParamValidator(),
            new SmsTemplateRenderer(new SmsTemplateBodyReader()),
            new PhoneNumberNormalizer(new StaticSystemConfigService()),
        );
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
        $gateway->strategy = SmsGatewayEntity::STRATEGY_EXCLUSIVE;
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
        $mapping->auditStatus = SmsTemplateMappingEntity::AUDIT_APPROVED;
        $mapping->templateCode = 'SMS_123456789';
        $mapping->signName = 'Shopwell';
        $mapping->gatewayId = $gateway?->id;

        return $mapping;
    }
}
