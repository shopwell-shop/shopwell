<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Command;

use Psr\Container\ContainerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Validates installed provider declarations before they can accept payments.
 *
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'payment:providers:validate',
    description: 'Validates installed payment providers: capabilities, products and decoration chains.',
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
            $products = (new PaymentProductRegistry($registry))->all();
        } catch (PaymentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($providers === []) {
            $io->warning('This installation declares no payment provider.');

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($providers as $technicalName => $provider) {
            $rows[] = [
                $technicalName,
                (string) \count($provider->products),
                $this->capabilitiesOf($provider->capabilities),
                $this->chainOf($technicalName),
            ];
        }

        $io->table(['provider', 'products', 'capabilities', 'decorated by'], $rows);
        $io->writeln(\sprintf(
            '%d provider(s), %d product(s).',
            \count($providers),
            \count($products)
        ));

        $problems = $this->problemsOf($providers);

        if ($problems !== []) {
            $io->error($problems);

            return Command::FAILURE;
        }

        $io->success('Every declared payment provider is coherent.');

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
            if ($provider->products === []) {
                $problems[] = \sprintf(
                    'The payment provider "%s" declares no payment product, so no app can collect through it.',
                    $technicalName
                );
            }

            if ($provider->credentials === []) {
                $problems[] = \sprintf(
                    'The payment provider "%s" declares no credential field, which no merchant account can be configured with.',
                    $technicalName
                );
            }
        }

        return $problems;
    }
}
