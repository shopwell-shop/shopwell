<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Command;

use Psr\Container\ContainerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the configuration rows whose interface or channel is no longer declared by this
 * installation.
 *
 * What a row like that actually does - verified, not assumed:
 *
 *  - the administration does **not** render it. `PaymentProviderConfigController::list()` walks
 *    `PaymentProviderRegistry::all()`, i.e. the declarations, and only looks up the stored row for a
 *    declared name - a row of an uninstalled interface is simply never asked for, so it produces no
 *    ghost channel on the configuration page.
 *  - the gateway never reads it either: every lookup - `PaymentConfigResolver::loadProviderConfig()`,
 *    `loadChannelConfig()`, `loadAppProvider()`, `loadAppChannel()` - is by technical name and is only
 *    reached through a declaration.
 *
 * So a residual row is invisible dead weight, and that is exactly the risk: because every lookup is
 * keyed by the technical name alone, the row is picked up again the moment any declaration uses that
 * name. A reinstalled extension - or another extension that happens to choose the same name - silently
 * inherits the sealed credentials, channel parameters and app authorizations of the interface that
 * used to carry it, without anyone typing a single value. Pruning means "a name that is not declared
 * carries nothing".
 *
 * It is a dry run by default: it prints per table how many rows are affected and which technical names
 * they carry, and only deletes with `--force`. An installation that declares no interface at all is
 * refused instead of pruned - there the whole configuration is "residual", which is a broken
 * installation and not a cleanup.
 *
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'payment:providers:prune',
    description: 'Removes the payment configuration rows whose interface or channel this installation does not declare anymore.',
)]
class PaymentProvidersPruneCommand extends Command
{
    private const OPTION_FORCE = 'force';

    /**
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     * @param EntityRepository<PaymentAppProviderCollection> $appProviderRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EntityRepository $providerConfigRepository,
        private readonly EntityRepository $channelConfigRepository,
        private readonly EntityRepository $appProviderRepository,
        private readonly EntityRepository $appChannelRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Actually delete the residual rows instead of only reporting them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // the registry is resolved here instead of being injected: it builds every declaration
            // while it is constructed, so a broken declaration would throw while the container wires
            // this command - before execute() runs and before anything can report it
            $providerRegistry = $this->container->get(PaymentProviderRegistry::class);
            \assert($providerRegistry instanceof PaymentProviderRegistry);

            $providers = $providerRegistry->all();
        } catch (PaymentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($providers === []) {
            // without a single declaration every stored row would look residual, which would turn a
            // broken installation into an empty configuration on the first `--force`
            $io->error('This installation declares no payment interface at all, so every stored row would look residual. Refusing to prune; check the payment declarations first.');

            return Command::FAILURE;
        }

        $providerNames = array_keys($providers);
        $channelNames = $this->declaredChannelNames($providers);

        $residual = [
            'payment_provider_config' => $this->residualProviderConfigs($providerNames),
            'payment_channel_config' => $this->residualChannelConfigs($providers),
            'payment_app_provider' => $this->residualAppProviders($providerNames),
            'payment_app_channel' => $this->residualAppChannels($channelNames),
        ];

        $rows = [];
        $total = 0;

        foreach ($residual as $table => $entries) {
            $count = \count($entries['ids']);
            $total += $count;

            $rows[] = [
                $table,
                $count,
                $entries['names'] === [] ? '-' : implode(', ', $entries['names']),
            ];
        }

        $io->table(['table', 'rows', 'technical names'], $rows);

        if ($total === 0) {
            $io->success('No stored configuration belongs to an interface or channel that is not declared anymore.');

            return Command::SUCCESS;
        }

        if (!$input->getOption(self::OPTION_FORCE)) {
            $io->warning(\sprintf(
                '%d row(s) belong to an interface or channel that is not declared anymore. Nothing was deleted - run again with --force to delete them.',
                $total
            ));

            return Command::SUCCESS;
        }

        $context = Context::createCLIContext();

        $this->providerConfigRepository->delete($this->idsOf($residual['payment_provider_config']['ids']), $context);
        $this->channelConfigRepository->delete($this->idsOf($residual['payment_channel_config']['ids']), $context);
        $this->appProviderRepository->delete($this->idsOf($residual['payment_app_provider']['ids']), $context);
        $this->appChannelRepository->delete($this->idsOf($residual['payment_app_channel']['ids']), $context);

        $io->success(\sprintf('%d residual row(s) deleted.', $total));

        return Command::SUCCESS;
    }

    /**
     * Every channel name the declarations carry, whichever interface offers it.
     *
     * The registry of the channels is deliberately not used here: it refuses to be built when two
     * interfaces claim the same name, which is a validation concern (`payment:providers:validate`) and
     * would block a cleanup this installation may well need.
     *
     * @param array<string, PaymentProvider> $providers
     *
     * @return list<string>
     */
    private function declaredChannelNames(array $providers): array
    {
        $names = [];

        foreach ($providers as $provider) {
            foreach ($provider->channels as $technicalName => $channel) {
                $names[$technicalName] = true;
            }
        }

        return $this->sortedKeys($names);
    }

    /**
     * The interfaces whose technical name no declaration carries anymore.
     *
     * @param list<string> $declaredProviders
     *
     * @return array{ids: list<string>, names: list<string>}
     */
    private function residualProviderConfigs(array $declaredProviders): array
    {
        $ids = [];
        $names = [];

        foreach ($this->providerConfigRepository->search(new Criteria(), Context::createCLIContext())->getEntities() as $config) {
            if (!$config instanceof PaymentProviderConfigEntity) {
                continue;
            }

            if (\in_array($config->providerTechnicalName, $declaredProviders, true)) {
                continue;
            }

            $ids[] = $config->id;
            $names[$config->providerTechnicalName] = true;
        }

        return ['ids' => $ids, 'names' => $this->sortedKeys($names)];
    }

    /**
     * The channel parameters that belong to an interface which is gone, or to a channel that interface
     * does not offer anymore.
     *
     * @param array<string, PaymentProvider> $providers
     *
     * @return array{ids: list<string>, names: list<string>}
     */
    private function residualChannelConfigs(array $providers): array
    {
        $ids = [];
        $names = [];

        foreach ($this->channelConfigRepository->search(new Criteria(), Context::createCLIContext())->getEntities() as $config) {
            if (!$config instanceof PaymentChannelConfigEntity) {
                continue;
            }

            $provider = $providers[$config->providerTechnicalName] ?? null;

            // a channel is addressed by its own technical name, so an interface that is gone makes
            // every one of its channels residual as well
            if ($provider !== null && $provider->hasChannel($config->channelTechnicalName)) {
                continue;
            }

            $ids[] = $config->id;
            $names[$config->providerTechnicalName . '/' . $config->channelTechnicalName] = true;
        }

        return ['ids' => $ids, 'names' => $this->sortedKeys($names)];
    }

    /**
     * The authorizations of the apps for interfaces that are no declaration anymore.
     *
     * @param list<string> $declaredProviders
     *
     * @return array{ids: list<string>, names: list<string>}
     */
    private function residualAppProviders(array $declaredProviders): array
    {
        $ids = [];
        $names = [];

        foreach ($this->appProviderRepository->search(new Criteria(), Context::createCLIContext())->getEntities() as $authorization) {
            if (!$authorization instanceof PaymentAppProviderEntity) {
                continue;
            }

            if (\in_array($authorization->providerTechnicalName, $declaredProviders, true)) {
                continue;
            }

            $ids[] = $authorization->id;
            $names[$authorization->providerTechnicalName] = true;
        }

        return ['ids' => $ids, 'names' => $this->sortedKeys($names)];
    }

    /**
     * The channels the apps have enabled that no declaration offers anymore.
     *
     * @param list<string> $declaredChannels
     *
     * @return array{ids: list<string>, names: list<string>}
     */
    private function residualAppChannels(array $declaredChannels): array
    {
        $ids = [];
        $names = [];

        foreach ($this->appChannelRepository->search(new Criteria(), Context::createCLIContext())->getEntities() as $channel) {
            if (!$channel instanceof PaymentAppChannelEntity) {
                continue;
            }

            if (\in_array($channel->channelTechnicalName, $declaredChannels, true)) {
                continue;
            }

            $ids[] = $channel->id;
            $names[$channel->channelTechnicalName] = true;
        }

        return ['ids' => $ids, 'names' => $this->sortedKeys($names)];
    }

    /**
     * The ids as the delete payload of the dal - an empty list stays empty, so a table without
     * residual rows is simply written nothing.
     *
     * @param list<string> $ids
     *
     * @return list<array{id: string}>
     */
    private function idsOf(array $ids): array
    {
        return array_map(static fn (string $id): array => ['id' => $id], $ids);
    }

    /**
     * Technical names are sets while they are collected - one name may own many rows - and are printed
     * sorted, so two runs produce the same output.
     *
     * @param array<string, true> $names
     *
     * @return list<string>
     */
    private function sortedKeys(array $names): array
    {
        $keys = array_keys($names);
        sort($keys);

        return $keys;
    }
}
