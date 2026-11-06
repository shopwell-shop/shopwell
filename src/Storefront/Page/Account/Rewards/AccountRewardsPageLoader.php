<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Rewards;

use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRoute;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyTierProgress;
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
class AccountRewardsPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoyaltyRoute $loyaltyRoute,
        private readonly AbstractTranslator $translator,
        private readonly LoyaltyTierProgress $tierProgress
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AccountRewardsPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountRewardsPage::createFrom($page);
        $this->setMetaInformation($page);

        $rewards = $this->loyaltyRoute->load($request, $salesChannelContext, $customer)->getLoyalty();

        $page->setRewards($rewards);

        /** @var list<array<string, mixed>> $tiers */
        $tiers = $rewards->get('tiers') ?? [];

        [$currentTier, $nextTier] = $this->tierProgress->resolve($tiers, $rewards->get('tierId'));
        $page->setCurrentTier($currentTier);
        $page->setNextTier($nextTier);

        $this->eventDispatcher->dispatch(
            new AccountRewardsPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountRewardsPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.rewardsMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }
}
