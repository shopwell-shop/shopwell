<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsGatewayCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsGatewayEntity;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateEntity;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateMappingCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateMappingEntity;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayResolver;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsGatewayResolver::class)]
class SmsGatewayResolverTest extends TestCase
{
    public function testPicksTheGatewayOfTheRegionOfTheRecipient(): void
    {
        $domestic = $this->createGateway('aliyun', priority: 1);
        $international = $this->createGateway('twilio', priority: 2);

        $resolver = $this->createResolver(
            [$international, $domestic],
            ['aliyun' => ['regions' => ['CN']], 'twilio' => ['regions' => ['GLOBAL']]],
        );

        // a gateway that lists GLOBAL serves China too, it is simply the later candidate
        static::assertSame(
            [$domestic->id, $international->id],
            $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN')
        );

        static::assertSame(
            [$international->id],
            $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'DE')
        );
    }

    public function testAGatewayWithoutRegionServesEveryCountry(): void
    {
        $international = $this->createGateway('twilio');

        $resolver = $this->createResolver([$international], ['twilio' => ['regions' => ['GLOBAL']]]);

        static::assertSame([$international->id], $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN'));
        static::assertSame([$international->id], $resolver->resolveGatewayIds(Context::createDefaultContext()));
    }

    public function testSkipsAGatewayOfAnotherSalesChannel(): void
    {
        $others = $this->createGateway('aliyun', salesChannelId: 'other-sales-channel');
        $shared = $this->createGateway('twilio');

        $resolver = $this->createResolver([$others, $shared], ['aliyun' => [], 'twilio' => []]);

        static::assertSame(
            [$shared->id],
            $resolver->resolveGatewayIds(Context::createDefaultContext(), 'my-sales-channel', 'CN')
        );
    }

    public function testKeepsAGatewayBoundToTheSalesChannelOfTheCaller(): void
    {
        $mine = $this->createGateway('aliyun', salesChannelId: 'my-sales-channel');

        $resolver = $this->createResolver([$mine], ['aliyun' => []]);

        static::assertSame([$mine->id], $resolver->resolveGatewayIds(Context::createDefaultContext(), 'my-sales-channel'));
        static::assertSame([], $resolver->resolveGatewayIds(Context::createDefaultContext(), 'other-sales-channel'));
    }

    public function testSkipsADisabledGateway(): void
    {
        $disabled = $this->createGateway('aliyun', enabled: false);

        $resolver = $this->createResolver([$disabled], ['aliyun' => []]);

        static::assertSame([], $resolver->resolveGatewayIds(Context::createDefaultContext()));
    }

    public function testSkipsAGatewayOfAProviderThePlatformDoesNotKnow(): void
    {
        // the row survives an uninstalled extension, the routing must not hand it a message
        $orphan = $this->createGateway('removed-provider');

        $resolver = $this->createResolver([$orphan], []);

        static::assertSame([], $resolver->resolveGatewayIds(Context::createDefaultContext()));
    }

    public function testOrdersTheCandidatesByTheirPriority(): void
    {
        $late = $this->createGateway('twilio', priority: 10);
        $early = $this->createGateway('aliyun', priority: 1);

        $resolver = $this->createResolver([$late, $early], ['aliyun' => [], 'twilio' => []]);

        static::assertSame(
            [$early->id, $late->id],
            $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN')
        );
    }

    public function testFallsBackToTheConfiguredDefaultWhenTheRegionMatchedNothing(): void
    {
        $regional = $this->createGateway('us-only');

        $resolver = $this->createResolver(
            [$regional],
            ['us-only' => ['regions' => ['US']]],
            config: ['core.smsSettings.defaultGateway' => 'us-only'],
        );

        // a Chinese number matches neither US nor GLOBAL, the configured direction is used
        static::assertSame([$regional->id], $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN'));
    }

    public function testReturnsNothingWithoutAMatchingGatewayOrADefault(): void
    {
        $resolver = $this->createResolver([], []);

        static::assertSame([], $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN'));
    }

    public function testReturnsNothingWhenSmsIsSwitchedOff(): void
    {
        $gateway = $this->createGateway('twilio');

        $resolver = $this->createResolver(
            [$gateway],
            ['twilio' => []],
            config: ['core.smsSettings.enabled' => false],
        );

        static::assertSame([], $resolver->resolveGatewayIds(Context::createDefaultContext()));
    }

    public function testSmsStaysOnWhenTheSettingWasNeverWritten(): void
    {
        $gateway = $this->createGateway('twilio');

        $resolver = $this->createResolver([$gateway], ['twilio' => []]);

        static::assertSame([$gateway->id], $resolver->resolveGatewayIds(Context::createDefaultContext()));
    }

    public function testSkipsAGatewayWithoutAnApprovedMappingOfTheTemplate(): void
    {
        $gateway = $this->createGateway('aliyun');
        $template = $this->createTemplate('customer.otp.login');

        $resolver = $this->createResolver(
            [$gateway],
            ['aliyun' => ['templateMode' => true]],
            [$this->createMapping($template, 'aliyun', SmsTemplateMappingEntity::AUDIT_PENDING, $gateway)],
        );

        static::assertSame([], $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN', $template));
    }

    public function testKeepsAGatewayWithAnApprovedMappingOfTheTemplate(): void
    {
        $gateway = $this->createGateway('aliyun');
        $template = $this->createTemplate('customer.otp.login');

        $resolver = $this->createResolver(
            [$gateway],
            ['aliyun' => ['templateMode' => true]],
            [$this->createMapping($template, 'aliyun', SmsTemplateMappingEntity::AUDIT_APPROVED, $gateway)],
        );

        static::assertSame([$gateway->id], $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN', $template));
    }

    public function testKeepsAGatewayWhereTheMappingHoldsForEveryAccountOfTheProvider(): void
    {
        $gateway = $this->createGateway('aliyun');
        $template = $this->createTemplate('customer.otp.login');

        $resolver = $this->createResolver(
            [$gateway],
            ['aliyun' => ['templateMode' => true]],
            [$this->createMapping($template, 'aliyun', SmsTemplateMappingEntity::AUDIT_APPROVED)],
        );

        static::assertSame([$gateway->id], $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN', $template));
    }

    public function testSkipsAGatewayWhoseMappingBelongsToAnotherAccountOfTheProvider(): void
    {
        $gateway = $this->createGateway('aliyun');
        $other = $this->createGateway('aliyun');
        $template = $this->createTemplate('customer.otp.login');

        $resolver = $this->createResolver(
            [$gateway],
            ['aliyun' => ['templateMode' => true]],
            [$this->createMapping($template, 'aliyun', SmsTemplateMappingEntity::AUDIT_APPROVED, $other)],
        );

        static::assertSame([], $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'CN', $template));
    }

    public function testAFreeTextGatewayNeedsNoMappingOfTheTemplate(): void
    {
        $gateway = $this->createGateway('twilio');
        $template = $this->createTemplate('customer.otp.login');

        $resolver = $this->createResolver([$gateway], ['twilio' => ['templateMode' => false]]);

        static::assertSame(
            [$gateway->id],
            $resolver->resolveGatewayIds(Context::createDefaultContext(), null, 'DE', $template)
        );
    }

    public function testTheAuditStateOfATemplateIsLookedUpOnlyOnce(): void
    {
        $gateway = $this->createGateway('aliyun');
        $template = $this->createTemplate('customer.otp.login');

        // the repository holds a single answer on purpose - a second lookup would fail
        $mappingRepository = StaticEntityRepository::of(SmsTemplateMappingCollection::class, [
            new SmsTemplateMappingCollection([
                $this->createMapping($template, 'aliyun', SmsTemplateMappingEntity::AUDIT_APPROVED, $gateway),
            ]),
        ]);

        $resolver = new SmsGatewayResolver(
            $this->createGatewayRepository([$gateway]),
            $mappingRepository,
            $this->createRegistry(['aliyun' => ['templateMode' => true]]),
            new StaticSystemConfigService(),
        );

        $context = Context::createDefaultContext();

        static::assertSame([$gateway->id], $resolver->resolveGatewayIds($context, null, 'CN', $template));
        static::assertSame([$gateway->id], $resolver->resolveGatewayIds($context, null, 'CN', $template));
    }

    /**
     * @param list<SmsGatewayEntity> $gateways
     * @param array<string, array{regions?: list<string>, templateMode?: bool}> $factories
     * @param list<SmsTemplateMappingEntity> $mappings
     * @param array<string, mixed> $config
     */
    private function createResolver(
        array $gateways,
        array $factories,
        array $mappings = [],
        array $config = [],
    ): SmsGatewayResolver {
        return new SmsGatewayResolver(
            $this->createGatewayRepository($gateways),
            StaticEntityRepository::of(SmsTemplateMappingCollection::class, [new SmsTemplateMappingCollection($mappings)]),
            $this->createRegistry($factories),
            new StaticSystemConfigService($config),
        );
    }

    /**
     * @param list<SmsGatewayEntity> $gateways
     *
     * @return StaticEntityRepository<SmsGatewayCollection>
     */
    private function createGatewayRepository(array $gateways): StaticEntityRepository
    {
        // the stub consumes one configured answer per lookup and the routing is read more than once
        $answer = static fn (Criteria $criteria): SmsGatewayCollection => new SmsGatewayCollection($gateways);

        return StaticEntityRepository::of(SmsGatewayCollection::class, array_fill(0, 4, $answer));
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

    private function createGateway(
        string $technicalName,
        bool $enabled = true,
        ?string $salesChannelId = null,
        int $priority = 0,
    ): SmsGatewayEntity {
        $gateway = new SmsGatewayEntity();
        $gateway->id = Uuid::randomHex();
        $gateway->setUniqueIdentifier($gateway->id);
        $gateway->technicalName = $technicalName;
        $gateway->enabled = $enabled;
        $gateway->salesChannelId = $salesChannelId;
        $gateway->priority = $priority;
        $gateway->strategy = SmsGatewayEntity::STRATEGY_EXCLUSIVE;
        $gateway->credentials = ['accessKeyId' => 'key'];
        $gateway->options = [];
        $gateway->label = 'test gateway';

        return $gateway;
    }

    private function createTemplate(string $technicalName): SmsTemplateEntity
    {
        $template = new SmsTemplateEntity();
        $template->id = Uuid::randomHex();
        $template->setUniqueIdentifier($template->id);
        $template->technicalName = $technicalName;
        $template->scene = SmsTemplateEntity::SCENE_OTP;
        $template->enabled = true;

        return $template;
    }

    private function createMapping(
        SmsTemplateEntity $template,
        string $technicalName,
        string $auditStatus,
        ?SmsGatewayEntity $gateway = null,
    ): SmsTemplateMappingEntity {
        $mapping = new SmsTemplateMappingEntity();
        $mapping->id = Uuid::randomHex();
        $mapping->setUniqueIdentifier($mapping->id);
        $mapping->templateId = $template->id;
        $mapping->technicalName = $technicalName;
        $mapping->auditStatus = $auditStatus;
        $mapping->templateCode = 'SMS_123456789';
        $mapping->signName = 'Shopwell';
        $mapping->gatewayId = $gateway?->id;

        return $mapping;
    }
}
