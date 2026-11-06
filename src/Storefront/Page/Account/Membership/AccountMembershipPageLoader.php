<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Membership;

use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipRoute;
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
class AccountMembershipPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MembershipRoute $membershipRoute,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AccountMembershipPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountMembershipPage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setMembership(
            $this->membershipRoute->load($request, $salesChannelContext, $customer)->getMembership()
        );

        $this->eventDispatcher->dispatch(
            new AccountMembershipPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountMembershipPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.membershipMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }
}
