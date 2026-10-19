<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\Command\PaymentProvidersPruneCommand;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The cleanup of the configuration rows of interfaces this installation does not declare anymore.
 *
 * The dangerous part is not the delete but the detection: a row may be residual because its interface
 * is gone or because the interface dropped that one channel, and a dry run must never write. Both are
 * asserted, and so is the refusal to prune an installation that declares nothing - there every row
 * would look residual.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProvidersPruneCommand::class)]
class PaymentProvidersPruneCommandTest extends TestCase
{
    public function testADryRunReportsTheResidualRowsAndDeletesNothing(): void
    {
        $providerConfigs = static::createMock(EntityRepository::class);
        $providerConfigs->method('search')->willReturn($this->searchResult(
            PaymentProviderConfigEntity::ENTITY_NAME,
            new PaymentProviderConfigCollection([$this->providerConfig('alipay_old')])
        ));
        $providerConfigs->expects($this->never())->method('delete');

        $channelConfigs = static::createMock(EntityRepository::class);
        $channelConfigs->method('search')->willReturn($this->searchResult(
            PaymentChannelConfigEntity::ENTITY_NAME,
            new PaymentChannelConfigCollection([
                $this->channelConfig('alipay', 'alipay_mp', 'channel-config-mp'),
                $this->channelConfig('alipay', 'alipay_h5', 'channel-config-h5'),
            ])
        ));
        $channelConfigs->expects($this->never())->method('delete');

        $appProviders = static::createMock(EntityRepository::class);
        $appProviders->method('search')->willReturn($this->searchResult(
            PaymentAppProviderEntity::ENTITY_NAME,
            new PaymentAppProviderCollection([$this->appProvider('alipay')])
        ));
        $appProviders->expects($this->never())->method('delete');

        $appChannels = static::createMock(EntityRepository::class);
        $appChannels->method('search')->willReturn($this->searchResult(
            PaymentAppChannelEntity::ENTITY_NAME,
            new PaymentAppChannelCollection([$this->appChannel('alipay_h5')])
        ));
        $appChannels->expects($this->never())->method('delete');

        $tester = $this->tester($providerConfigs, $channelConfigs, $appProviders, $appChannels);

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        // only the provider that is not declared and the channel the declared interface dropped
        static::assertStringContainsString('alipay_old', $display);
        static::assertStringContainsString('alipay/alipay_mp', $display);
        static::assertStringContainsString('Nothing was deleted', $display);
        // the declared channel is not residual and must not be named
        static::assertStringNotContainsString('alipay/alipay_h5', $display);
    }

    public function testEverythingDeclaredReportsNothingToDo(): void
    {
        $providerConfigs = static::createMock(EntityRepository::class);
        $providerConfigs->method('search')->willReturn($this->searchResult(
            PaymentProviderConfigEntity::ENTITY_NAME,
            new PaymentProviderConfigCollection([$this->providerConfig('alipay')])
        ));
        $providerConfigs->expects($this->never())->method('delete');

        $channelConfigs = static::createMock(EntityRepository::class);
        $channelConfigs->method('search')->willReturn($this->searchResult(
            PaymentChannelConfigEntity::ENTITY_NAME,
            new PaymentChannelConfigCollection([$this->channelConfig('alipay', 'alipay_h5')])
        ));
        $channelConfigs->expects($this->never())->method('delete');

        $appProviders = static::createMock(EntityRepository::class);
        $appProviders->method('search')->willReturn($this->searchResult(
            PaymentAppProviderEntity::ENTITY_NAME,
            new PaymentAppProviderCollection([$this->appProvider('alipay')])
        ));
        $appProviders->expects($this->never())->method('delete');

        $appChannels = static::createMock(EntityRepository::class);
        $appChannels->method('search')->willReturn($this->searchResult(
            PaymentAppChannelEntity::ENTITY_NAME,
            new PaymentAppChannelCollection([$this->appChannel('alipay_h5')])
        ));
        $appChannels->expects($this->never())->method('delete');

        $tester = $this->tester($providerConfigs, $channelConfigs, $appProviders, $appChannels);

        static::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));

        static::assertStringContainsString('No stored configuration belongs to an interface or channel', $tester->getDisplay());
    }

    public function testForceDeletesTheResidualRowsOfEveryTable(): void
    {
        $providerConfigs = static::createMock(EntityRepository::class);
        $providerConfigs->method('search')->willReturn($this->searchResult(
            PaymentProviderConfigEntity::ENTITY_NAME,
            new PaymentProviderConfigCollection([$this->providerConfig('alipay_old', 'provider-config-id')])
        ));
        $providerConfigs->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $ids): bool => $ids === [['id' => 'provider-config-id']]));

        $channelConfigs = static::createMock(EntityRepository::class);
        $channelConfigs->method('search')->willReturn($this->searchResult(
            PaymentChannelConfigEntity::ENTITY_NAME,
            new PaymentChannelConfigCollection([$this->channelConfig('alipay_old', 'alipay_h5', 'channel-config-id')])
        ));
        $channelConfigs->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $ids): bool => $ids === [['id' => 'channel-config-id']]));

        $appProviders = static::createMock(EntityRepository::class);
        $appProviders->method('search')->willReturn($this->searchResult(
            PaymentAppProviderEntity::ENTITY_NAME,
            new PaymentAppProviderCollection([$this->appProvider('alipay_old', 'app-provider-id')])
        ));
        $appProviders->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $ids): bool => $ids === [['id' => 'app-provider-id']]));

        $appChannels = static::createMock(EntityRepository::class);
        $appChannels->method('search')->willReturn($this->searchResult(
            PaymentAppChannelEntity::ENTITY_NAME,
            new PaymentAppChannelCollection([$this->appChannel('alipay_mp', 'app-channel-id')])
        ));
        $appChannels->expects($this->once())
            ->method('delete')
            ->with(static::callback(static fn (array $ids): bool => $ids === [['id' => 'app-channel-id']]));

        $tester = $this->tester($providerConfigs, $channelConfigs, $appProviders, $appChannels);

        static::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));

        static::assertStringContainsString('4 residual row(s) deleted.', $tester->getDisplay());
    }

    /**
     * Without a single declaration every stored row would look residual - pruning would then empty a
     * broken installation instead of cleaning it up.
     */
    public function testRefusesToPruneWhenNoInterfaceIsDeclared(): void
    {
        $providerConfigs = static::createMock(EntityRepository::class);
        $providerConfigs->expects($this->never())->method('search');
        $providerConfigs->expects($this->never())->method('delete');

        $tester = $this->tester(
            $providerConfigs,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            declared: false
        );

        static::assertSame(Command::FAILURE, $tester->execute(['--force' => true]));

        static::assertStringContainsString('Refusing to prune', $tester->getDisplay());
    }

    /**
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigs
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigs
     * @param EntityRepository<PaymentAppProviderCollection> $appProviders
     * @param EntityRepository<PaymentAppChannelCollection> $appChannels
     */
    private function tester(
        EntityRepository $providerConfigs,
        EntityRepository $channelConfigs,
        EntityRepository $appProviders,
        EntityRepository $appChannels,
        bool $declared = true
    ): CommandTester {
        $declarations = $declared ? [$this->provider('alipay', 'alipay_h5')] : [];

        // a locator instead of the registry: it is constructed on the first `get()`, which is what
        // makes a broken declaration reportable by the command
        return new CommandTester(new PaymentProvidersPruneCommand(
            new ServiceLocator([
                PaymentProviderRegistry::class => static fn (): PaymentProviderRegistry => new PaymentProviderRegistry($declarations),
            ]),
            $providerConfigs,
            $channelConfigs,
            $appProviders,
            $appChannels,
        ));
    }

    private function provider(string $technicalName, string $channel): PaymentProvider
    {
        return new PaymentProvider(
            technicalName: $technicalName,
            label: 'sw-settings-payment-provider.provider.' . $technicalName,
            credentials: [],
            channels: [
                $channel => new PaymentChannel(
                    technicalName: $channel,
                    providerTechnicalName: $technicalName,
                    label: 'test.channel.label',
                    flow: PaymentFlow::REDIRECT,
                ),
            ],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: [PaymentCapability::CAPTURE],
        );
    }

    private function providerConfig(string $provider, string $id = 'id'): PaymentProviderConfigEntity
    {
        $config = new PaymentProviderConfigEntity();
        $config->id = $id;
        $config->setUniqueIdentifier($id);
        $config->providerTechnicalName = $provider;

        return $config;
    }

    private function channelConfig(string $provider, string $channel, string $id = 'id'): PaymentChannelConfigEntity
    {
        $config = new PaymentChannelConfigEntity();
        $config->id = $id;
        $config->setUniqueIdentifier($id);
        $config->providerTechnicalName = $provider;
        $config->channelTechnicalName = $channel;

        return $config;
    }

    private function appProvider(string $provider, string $id = 'id'): PaymentAppProviderEntity
    {
        $authorization = new PaymentAppProviderEntity();
        $authorization->id = $id;
        $authorization->setUniqueIdentifier($id);
        $authorization->providerTechnicalName = $provider;

        return $authorization;
    }

    private function appChannel(string $channel, string $id = 'id'): PaymentAppChannelEntity
    {
        $enabled = new PaymentAppChannelEntity();
        $enabled->id = $id;
        $enabled->setUniqueIdentifier($id);
        $enabled->channelTechnicalName = $channel;

        return $enabled;
    }

    /**
     * @template TElement of Entity
     *
     * @param EntityCollection<TElement> $entities
     *
     * @return EntitySearchResult<EntityCollection<TElement>>
     */
    private function searchResult(string $entityName, EntityCollection $entities): EntitySearchResult
    {
        return new EntitySearchResult(
            $entityName,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
