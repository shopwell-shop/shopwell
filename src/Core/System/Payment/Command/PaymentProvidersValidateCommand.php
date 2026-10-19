<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Command;

use Psr\Container\ContainerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Answers "what does this installation actually offer, and who covers what" for CI and for an
 * operator.
 *
 * Every check the declaration mechanism performs is a check on something a developer can get wrong -
 * a capability announced without an action, a payout notification declared without a verifier, a
 * channel declared by two interfaces, a technical name the notify url cannot carry. The container
 * compile catches the second of those, the first resolution of the registry catches the rest, and this
 * command turns both into a build step: it fails, so the failure happens before the plugin is installed
 * instead of at the first payment of a shopper.
 *
 * It also prints the decoration chain of every interface, which is the only place an operator can see
 * that a plugin replaced or extended a single step of the core implementation.
 *
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'payment:providers:validate',
    description: 'Validates the payment interfaces this installation declares: capabilities, channels and decoration chains.',
)]
class PaymentProvidersValidateCommand extends Command
{
    /**
     * @param array<string, list<string>> $decorationChain technical name => declaration classes, outermost first
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $decorationChain = [],
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // The registry is resolved here instead of being injected: it builds every declaration when
            // it is constructed, so a broken declaration would throw while the container wires this
            // command - before execute() runs, and before anything can report it. Resolving it inside
            // the command is what turns "the container does not build" into an error message.
            $registry = $this->container->get(PaymentProviderRegistry::class);
            \assert($registry instanceof PaymentProviderRegistry);

            $providers = $registry->all();
            // The channel index is built here for the same reason: it refuses to be built when two
            // interfaces claim the same channel name.
            $channels = (new PaymentChannelRegistry($registry))->all();
        } catch (PaymentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($providers === []) {
            $io->warning('This installation declares no payment interface at all.');

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($providers as $technicalName => $provider) {
            $rows[] = [
                $technicalName,
                (string) \count($provider->channels),
                $this->capabilitiesOf($provider->capabilities),
                $this->chainOf($technicalName),
            ];
        }

        $io->table(['interface', 'channels', 'capabilities', 'decorated by'], $rows);
        $io->writeln(\sprintf(
            '%d interface(s), %d channel(s).',
            \count($providers),
            \count($channels)
        ));

        $problems = $this->problemsOf($providers);

        if ($problems !== []) {
            $io->error($problems);

            return Command::FAILURE;
        }

        $io->success('Every declared payment interface is coherent.');

        return Command::SUCCESS;
    }

    /**
     * @param list<PaymentCapability> $capabilities
     */
    private function capabilitiesOf(array $capabilities): string
    {
        $values = array_map(
            static fn (PaymentCapability $capability): string => $capability->value,
            $capabilities
        );

        sort($values);

        return implode(', ', $values);
    }

    private function chainOf(string $technicalName): string
    {
        $chain = $this->decorationChain[$technicalName] ?? [];

        if ($chain === []) {
            // validation ran outside of a compiled container - the registry is the only source then
            return 'unknown';
        }

        if (\count($chain) === 1) {
            // only the declaration itself: nothing decorates the interface, so there is no chain to print
            return '-';
        }

        // stored outermost first, which is also the order that reads as "my decorator < the interface"
        return implode(' < ', $chain);
    }

    /**
     * The two questions the registry itself cannot answer, because it never sees the declarations.
     *
     * @param array<string, PaymentProvider> $providers
     *
     * @return list<string>
     */
    private function problemsOf(array $providers): array
    {
        $problems = [];

        foreach ($providers as $technicalName => $provider) {
            if ($provider->channels === []) {
                $problems[] = \sprintf(
                    'The payment interface "%s" declares no payment channel, so no app can collect through it.',
                    $technicalName
                );
            }

            if ($provider->credentials === []) {
                $problems[] = \sprintf(
                    'The payment interface "%s" declares no credential field, which no merchant account can be configured with.',
                    $technicalName
                );
            }
        }

        return $problems;
    }
}
