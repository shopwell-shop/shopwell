<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Loyalty;

use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRoute;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountLoyaltyPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoyaltyRoute $loyaltyRoute,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AccountLoyaltyPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountLoyaltyPage::createFrom($page);
        $this->setMetaInformation($page);

        $loyalty = $this->loyaltyRoute->load($request, $salesChannelContext, $customer)->getLoyalty();

        $page->setLoyalty($loyalty);

        /** @var list<array<string, mixed>> $tiers */
        $tiers = $loyalty->get('tiers') ?? [];

        [$currentTier, $nextTier] = $this->resolveTiers($tiers, $loyalty->get('membershipTierId'));
        $page->setCurrentTier($currentTier);
        $page->setNextTier($nextTier);

        $this->eventDispatcher->dispatch(
            new AccountLoyaltyPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountLoyaltyPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.loyaltyMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    /**
     * @param list<array<string, mixed>> $tiers
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    private function resolveTiers(array $tiers, mixed $membershipTierId): array
    {
        $currentTier = null;

        foreach ($tiers as $tier) {
            if (($tier['id'] ?? null) === $membershipTierId) {
                $currentTier = $tier;
                break;
            }
        }

        if ($currentTier === null) {
            return [null, $tiers[0] ?? null];
        }

        $nextTier = null;

        foreach ($tiers as $tier) {
            if (($tier['minGrowthValue'] ?? 0) > ($currentTier['minGrowthValue'] ?? 0)) {
                $nextTier = $tier;
                break;
            }
        }

        return [$currentTier, $nextTier];
    }
}
