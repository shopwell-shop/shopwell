<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\SalesChannel;

use Shopwell\Core\Content\Cms\CmsException;
use Shopwell\Core\Content\Cms\Exception\PageNotFoundException;
use Shopwell\Core\Content\Cms\Extension\CmsRouteExtension;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CmsRoute extends AbstractCmsRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractCmsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/cms/{id}',
        name: 'store-api.cms.detail',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $id, Request $request, SalesChannelContext $context): CmsRouteResponse
    {
        return $this->extensions->publish(
            name: CmsRouteExtension::NAME,
            extension: new CmsRouteExtension($id, $request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $id, Request $request, SalesChannelContext $context): CmsRouteResponse
    {
        $criteria = new Criteria([$id]);

        $slots = RequestParamHelper::get($request, 'slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (\is_array($slots) && $slots !== []) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        $cmsPage = $this->cmsPageLoader->load($request, $criteria, $context)->getEntities()->first();
        if ($cmsPage === null) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore shopwell.domainException (Will be fixed with next major) */
                throw new PageNotFoundException($id);
            }
            throw CmsException::pageNotFound($id);
        }

        return new CmsRouteResponse($cmsPage);
    }
}
