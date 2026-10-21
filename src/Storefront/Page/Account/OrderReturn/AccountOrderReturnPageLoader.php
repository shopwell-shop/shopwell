<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\OrderReturn;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRoute;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('after-sales')]
class AccountOrderReturnPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OrderReturnRoute $orderReturnRoute,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function loadList(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AccountOrderReturnPage
    {
        $page = $this->load($request, $salesChannelContext, 'account.orderReturnMetaTitle');

        $returns = $this->orderReturnRoute->load($request, $salesChannelContext, $customer)->getObject();

        /** @var list<array<string, mixed>> $orderReturns */
        $orderReturns = $returns->get('orderReturns') ?? [];

        $page->setReturns($orderReturns);

        $this->dispatch($page, $request, $salesChannelContext);

        return $page;
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function loadDetail(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer, string $returnId): AccountOrderReturnPage
    {
        $page = $this->load($request, $salesChannelContext, 'account.orderReturnMetaTitle');

        $detail = $this->orderReturnRoute->detail($returnId, $salesChannelContext, $customer)->getObject();

        /** @var array<string, mixed>|null $orderReturn */
        $orderReturn = $detail->get('orderReturn');
        $page->setCurrent($orderReturn);

        $this->dispatch($page, $request, $salesChannelContext);

        return $page;
    }

    /**
     * The positions an order may still be returned for, plus the reasons to pick from.
     *
     * @throws CategoryNotFoundException
     */
    public function loadCreate(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer, string $orderId): AccountOrderReturnPage
    {
        $page = $this->load($request, $salesChannelContext, 'account.orderReturnCreateMetaTitle');

        $positions = $this->orderReturnRoute->positions($orderId, $salesChannelContext, $customer)->getObject();

        /** @var list<array<string, mixed>> $returnable */
        $returnable = $positions->get('positions') ?? [];
        /** @var list<array<string, mixed>> $reasons */
        $reasons = $positions->get('reasons') ?? [];

        $page->setPositions($returnable);
        $page->setReasons($reasons);
        $page->setOrderNumber($positions->get('orderNumber'));

        $this->dispatch($page, $request, $salesChannelContext);

        return $page;
    }

    /**
     * @throws CategoryNotFoundException
     */
    private function load(Request $request, SalesChannelContext $salesChannelContext, string $metaTitleKey): AccountOrderReturnPage
    {
        $genericPage = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountOrderReturnPage::createFrom($genericPage);
        $this->setMetaInformation($page, $metaTitleKey);

        return $page;
    }

    private function dispatch(AccountOrderReturnPage $page, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $this->eventDispatcher->dispatch(
            new AccountOrderReturnPageLoadedEvent($page, $salesChannelContext, $request)
        );
    }

    private function setMetaInformation(AccountOrderReturnPage $page, string $metaTitleKey): void
    {
        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setRobots('noindex,follow');

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans($metaTitleKey) . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }
}
