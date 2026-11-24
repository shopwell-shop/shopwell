<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Document;

use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopwell\Core\Content\Product\Extension\ProductDocumentRouteExtension;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductDocumentRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     * @param EntityRepository<ProductDocumentCollection> $productDocumentRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $productDocumentRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/product/{productId}/documents',
        name: 'store-api.product.documents',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDocumentDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDocumentRouteResponse
    {
        return $this->extensions->publish(
            name: ProductDocumentRouteExtension::NAME,
            extension: new ProductDocumentRouteExtension($productId, $request, $context, $criteria),
            function: fn (string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDocumentRouteResponse => $this->_load($productId, $context, $criteria),
        );
    }

    private function _load(string $productId, SalesChannelContext $context, Criteria $criteria): ProductDocumentRouteResponse
    {
        $this->cacheTagCollector->addTag(ProductDetailRoute::buildName($this->getVisibleParentId($productId, $context)));

        $media = $this->loadPublicMedia($productId, $context->getContext());

        $criteria->setTitle('product-document-route');
        $criteria->resetFields();
        $criteria->removeAssociation('product');
        if ($criteria->hasAssociation('translations')) {
            $criteria->getAssociation('translations')->removeAssociation('media');
        }
        $criteria->addFilter(
            new EqualsFilter('product.id', $productId),
            new EqualsAnyFilter('mediaId', array_values($media->getIds())),
        );
        if ($criteria->getSorting() === []) {
            $criteria->addSorting(new FieldSorting('position'), new FieldSorting('id'));
        }

        if ($media->count() === 0 && $criteria->getAggregations() === []) {
            return new ProductDocumentRouteResponse(new EntitySearchResult(
                ProductDocumentDefinition::ENTITY_NAME,
                0,
                new ProductDocumentCollection(),
                null,
                $criteria,
                $context->getContext(),
            ));
        }

        $result = $this->productDocumentRepository->search($criteria, $context->getContext());

        foreach ($result->getEntities() as $document) {
            $mediaId = $document->getTranslation('mediaId');
            $document->setMedia(\is_string($mediaId) ? $media->get($mediaId) : null);
        }

        return new ProductDocumentRouteResponse($result);
    }

    private function getVisibleParentId(string $productId, SalesChannelContext $context): string
    {
        if (!Uuid::isValid($productId)) {
            throw ProductException::productNotFound($productId);
        }

        $criteria = new Criteria([$productId]);
        $criteria->setTitle('product-document-route::product');
        $criteria->addFields(['parentId']);

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();
        if ($product === null) {
            throw ProductException::productNotFound($productId);
        }

        $parentId = $product->get('parentId');

        return \is_string($parentId) ? $parentId : $productId;
    }

    private function loadPublicMedia(string $productId, Context $context): MediaCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-document-route::media-ids');
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        $criteria->addAggregation(new TermsAggregation('media-ids', 'mediaId'));

        $mediaIds = $this->productDocumentRepository->aggregate($criteria, $context)->get('media-ids');
        if (!$mediaIds instanceof TermsResult || $mediaIds->getKeys() === []) {
            return new MediaCollection();
        }

        $mediaCriteria = new Criteria($mediaIds->getKeys());
        $mediaCriteria->setTitle('product-document-route::media');
        $mediaCriteria->addFilter(
            new EqualsFilter('private', false),
            new NotEqualsFilter('path', null),
        );

        return $this->mediaRepository->search($mediaCriteria, $context)->getEntities();
    }
}
