<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\TaxProvider;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Exception\TaxProviderExceptions;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopwell\Core\Framework\App\Privileges\AppCapability;
use Shopwell\Core\Framework\App\TaxProvider\Payload\TaxProviderPayload;
use Shopwell\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\TaxProvider\TaxProviderCollection;
use Shopwell\Core\System\TaxProvider\TaxProviderEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Package('checkout')]
class TaxProviderProcessor
{
    /**
     * @internal
     *
     * @param EntityRepository<TaxProviderCollection> $taxProviderRepository
     */
    public function __construct(
        private readonly EntityRepository $taxProviderRepository,
        private readonly LoggerInterface $logger,
        private readonly TaxAdjustment $adjustment,
        private readonly TaxProviderRegistry $registry,
        private readonly TaxProviderPayloadService $payloadService,
        private readonly AppCapability $appCapability
    ) {
    }

    public function process(Cart $cart, SalesChannelContext $context): void
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
            return;
        }

        $taxProviders = $this->getTaxProviders($context);

        if ($taxProviders->count() === 0) {
            return;
        }

        $exceptions = new TaxProviderExceptions();

        $result = $this->buildTaxes(
            $taxProviders,
            $cart,
            $context,
            $exceptions
        );

        if ($exceptions->hasExceptions()) {
            $this->logger->error($exceptions->getMessage(), ['error' => $exceptions]);

            throw $exceptions;
        }

        if (!$result) {
            return;
        }

        $this->adjustment->adjust($cart, $result, $context);
    }

    private function getTaxProviders(SalesChannelContext $context): TaxProviderCollection
    {
        $criteria = (new Criteria())
            ->addAssociations(['availabilityRule', 'app'])
            ->addFilter(
                new AndFilter([
                    new EqualsFilter('active', true),
                    new OrFilter([
                        new EqualsFilter('availabilityRuleId', null),
                        new EqualsAnyFilter('availabilityRuleId', $context->getRuleIds()),
                    ]),
                ])
            );

        $providers = $this->taxProviderRepository->search($criteria, $context->getContext())->getEntities();

        // we can safely sort the providers in php, as we do not expect more than a couple of providers
        // otherwise we would need to sort them in the database with an index many fields to be performant
        $providers->sortByPriority();

        return $providers;
    }

    private function buildTaxes(
        TaxProviderCollection $providers,
        Cart $cart,
        SalesChannelContext $context,
        TaxProviderExceptions $exceptions,
    ): ?TaxProviderResult {
        /** @var TaxProviderEntity $providerEntity */
        foreach ($providers->getElements() as $providerEntity) {
            // app providers
            $app = $providerEntity->getApp();
            $processUrl = $providerEntity->getProcessUrl();
            if ($app !== null && $processUrl !== null) {
                // do not push cart/customer data to an app that has not been granted the tax provider permission
                $result = $this->appCapability->whenGranted(
                    $app->getId(),
                    Tax::PERMISSION,
                    fn () => $this->handleAppRequest($app, $processUrl, $cart, $context)
                );

                if ($result !== null) {
                    return $result;
                }

                continue;
            }

            $provider = $this->registry->get($providerEntity->getIdentifier());

            if (!$provider) {
                $exceptions->add(
                    $providerEntity->getIdentifier(),
                    new NotFoundHttpException(\sprintf('No tax provider found for identifier %s', $providerEntity->getIdentifier()))
                );

                continue;
            }

            try {
                $taxProviderStruct = $provider->provide($cart, $context);
            } catch (\Throwable $e) {
                $exceptions->add($providerEntity->getIdentifier(), $e);

                continue;
            }

            // taxes given - no need to continue
            if ($taxProviderStruct->declaresTaxes()) {
                return $taxProviderStruct;
            }
        }

        return null;
    }

    private function handleAppRequest(
        AppEntity $app,
        string $processUrl,
        Cart $cart,
        SalesChannelContext $context
    ): ?TaxProviderResult {
        return $this->payloadService->request(
            $processUrl,
            new TaxProviderPayload($cart, $context),
            $app,
            $context->getContext()
        );
    }
}
