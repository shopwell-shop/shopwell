<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\OrderReturn;

use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnGuestRoute;
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
 *
 * The token is handed in by the controller, which keeps it in the session: the loader never decides
 * on its own whose returns it is loading, and a shop that has no token simply gets the empty form.
 */
#[Package('after-sales')]
class GuestOrderReturnPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OrderReturnGuestRoute $guestRoute,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @param string|null $token the token of a guest that already proved the order is theirs
     *
     * @throws CategoryNotFoundException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, ?string $token): GuestOrderReturnPage
    {
        $page = $this->createPage($request, $salesChannelContext, 'account.orderReturnGuestMetaTitle');

        if ($token !== null && $token !== '') {
            try {
                $this->fillFromToken($page, $request, $salesChannelContext, $token);
            } catch (OrderReturnException) {
                // an expired or forged token is not an error to report: the guest simply proves the
                // order again, which is what the controller does with the session
                $page->setVerified(false);
            }
        }

        $this->dispatch($page, $request, $salesChannelContext);

        return $page;
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function loadDetail(Request $request, SalesChannelContext $salesChannelContext, string $token, string $returnId): GuestOrderReturnPage
    {
        $page = $this->createPage($request, $salesChannelContext, 'account.orderReturnGuestMetaTitle');

        $detail = $this->guestRoute->detail($returnId, $this->requestWithToken($request, $token), $salesChannelContext)->getObject();

        /** @var array<string, mixed>|null $orderReturn */
        $orderReturn = $detail->get('orderReturn');

        $page->setVerified($orderReturn !== null);
        $page->setCurrent($orderReturn);

        $this->dispatch($page, $request, $salesChannelContext);

        return $page;
    }

    /**
     * The form a guest fills in before any token exists.
     *
     * @throws CategoryNotFoundException
     */
    public function loadForm(Request $request, SalesChannelContext $salesChannelContext): GuestOrderReturnPage
    {
        $page = $this->createPage($request, $salesChannelContext, 'account.orderReturnGuestMetaTitle');

        $this->dispatch($page, $request, $salesChannelContext);

        return $page;
    }

    /**
     * @throws CategoryNotFoundException
     */
    private function fillFromToken(GuestOrderReturnPage $page, Request $request, SalesChannelContext $salesChannelContext, string $token): void
    {
        $guestRequest = $this->requestWithToken($request, $token);

        $list = $this->guestRoute->load($guestRequest, $salesChannelContext)->getObject();

        /** @var list<array<string, mixed>> $returns */
        $returns = $list->get('orderReturns') ?? [];
        /** @var list<array<string, mixed>> $positions */
        $positions = $list->get('positions') ?? [];
        /** @var list<array<string, mixed>> $reasons */
        $reasons = $list->get('reasons') ?? [];

        $page->setVerified(true);
        $page->setReturns($returns);
        $page->setPositions($positions);
        $page->setReasons($reasons);

        $orderNumber = $list->get('orderNumber');

        if (\is_string($orderNumber) && $orderNumber !== '') {
            $page->setOrderNumber($orderNumber);
        }
    }

    /**
     * The route reads the token from the query, so the loader hands it a request that carries it -
     * without touching the request the storefront is rendering.
     */
    private function requestWithToken(Request $request, string $token): Request
    {
        return $request->duplicate(['token' => $token]);
    }

    /**
     * @throws CategoryNotFoundException
     */
    private function createPage(Request $request, SalesChannelContext $salesChannelContext, string $metaTitleKey): GuestOrderReturnPage
    {
        $genericPage = $this->genericLoader->load($request, $salesChannelContext);

        $page = GuestOrderReturnPage::createFrom($genericPage);

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setRobots('noindex,nofollow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans($metaTitleKey) . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );

        return $page;
    }

    private function dispatch(GuestOrderReturnPage $page, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $this->eventDispatcher->dispatch(
            new GuestOrderReturnPageLoadedEvent($page, $salesChannelContext, $request)
        );
    }
}
