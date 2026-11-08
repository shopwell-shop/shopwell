<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Maintenance;

use Shopwell\Core\Framework\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Controller\Exception\StorefrontException;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class MaintenancePageLoader
{
    public function __construct(
        private readonly AbstractContentRoute $contentRoute,
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(string $layoutId, Request $request, SalesChannelContext $context): MaintenancePage
    {
        $page = MaintenancePage::createFrom($this->genericLoader->load($request, $context));
        $response = $this->contentRoute->load('/layout/' . $layoutId, $request, $context);
        if (!$response instanceof ContentRouteResponse) {
            throw StorefrontException::contentRequiresTwigOutput();
        }

        $page->setContentPage($response->getContentPage());
        $this->eventDispatcher->dispatch(new MaintenancePageLoadedEvent($page, $context, $request));

        return $page;
    }
}
