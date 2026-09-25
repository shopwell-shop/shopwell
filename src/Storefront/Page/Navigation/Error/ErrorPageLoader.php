<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Navigation\Error;

use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Content\Cms\CmsException;
use Shopwell\Core\Content\Cms\Exception\PageNotFoundException;
use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
class ErrorPageLoader implements ErrorPageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws CmsException|PageNotFoundException
     */
    public function load(string $cmsErrorLayoutId, Request $request, SalesChannelContext $context): ErrorPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = ErrorPage::createFrom($page);

        $pages = $this->cmsPageLoader->load($request, new Criteria([$cmsErrorLayoutId]), $context)->getEntities();

        $cmsPage = $pages->first();
        if ($cmsPage === null) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore shopwell.domainException (Will be fixed with next major) */
                throw new PageNotFoundException($cmsErrorLayoutId);
            }
            throw CmsException::pageNotFound($cmsErrorLayoutId);
        }

        $page->setCmsPage($cmsPage);

        $this->eventDispatcher->dispatch(new ErrorPageLoadedEvent($page, $context, $request));

        return $page;
    }
}
