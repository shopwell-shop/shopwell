<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LandingPage\SalesChannel;

use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopwell\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopwell\Core\Content\LandingPage\Extension\LandingPageRouteExtension;
use Shopwell\Core\Content\LandingPage\LandingPageCollection;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Content\LandingPage\LandingPageException;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LandingPageRoute extends AbstractLandingPageRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<LandingPageCollection> $landingPageRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $landingPageRepository,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly EntityCmsSlotConfigInheritanceBuilder $cmsSlotConfigInheritanceBuilder,
        private readonly LandingPageDefinition $landingPageDefinition,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'landing-page-route-' . $id;
    }

    public function getDecorated(): AbstractLandingPageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/landing-page/{landingPageId}',
        name: 'store-api.landing-page.detail',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $landingPageId, Request $request, SalesChannelContext $context): LandingPageRouteResponse
    {
        return $this->extensions->publish(
            name: LandingPageRouteExtension::NAME,
            extension: new LandingPageRouteExtension($landingPageId, $request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $landingPageId, Request $request, SalesChannelContext $context): LandingPageRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($landingPageId));

        $landingPage = $this->loadLandingPage($landingPageId, $context);

        $pageId = $landingPage->getCmsPageId();

        if (!$pageId) {
            return new LandingPageRouteResponse($landingPage);
        }

        $resolverContext = new EntityResolverContext($context, $request, $this->landingPageDefinition, $landingPage);

        $pages = $this->cmsPageLoader->load(
            $request,
            $this->createCriteria($pageId, $request),
            $context,
            $this->buildMergedCmsSlotConfig($landingPage, $context),
            $resolverContext
        );

        $cmsPage = $pages->getEntities()->first();
        if ($cmsPage === null) {
            throw LandingPageException::notFound($pageId);
        }

        $landingPage->setCmsPage($cmsPage);

        return new LandingPageRouteResponse($landingPage);
    }

    private function loadLandingPage(string $landingPageId, SalesChannelContext $context): LandingPageEntity
    {
        $criteria = new Criteria([$landingPageId]);
        $criteria->setTitle('landing-page::data');

        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));
        $criteria->addAssociation('translations');

        $landingPage = $this->landingPageRepository->search($criteria, $context)->getEntities()->get($landingPageId);
        if (!$landingPage instanceof LandingPageEntity) {
            throw LandingPageException::notFound($landingPageId);
        }

        return $landingPage;
    }

    private function createCriteria(string $pageId, Request $request): Criteria
    {
        $criteria = new Criteria([$pageId]);
        $criteria->setTitle('landing-page::cms-page');

        $slots = RequestParamHelper::get($request, 'slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (\is_array($slots) && $slots !== []) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        return $criteria;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function buildMergedCmsSlotConfig(LandingPageEntity $landingPage, SalesChannelContext $context): ?array
    {
        return $this->cmsSlotConfigInheritanceBuilder->build(
            $landingPage->getTranslations(),
            $context,
        );
    }
}
