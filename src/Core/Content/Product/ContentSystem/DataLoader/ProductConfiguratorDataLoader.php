<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\ContentSystem\DataLoader;

use Shopwell\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopwell\Core\Content\Product\SalesChannel\Detail\PartialProductConfiguratorLoader;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorProductData;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopwell\Core\Content\Property\PropertyGroupCollection;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<PropertyGroupCollection>
 */
#[Package('discovery')]
class ProductConfiguratorDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_configurator';

    /**
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly PartialProductConfiguratorLoader $configuratorLoader,
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification(
                'productId',
                ConfigKeyKind::PropertyReference,
                'string',
                required: false,
                hasDefault: true,
                default: 'productId',
            ),
        ]);
    }

    /**
     * Data provided through the page context is not available to data loaders; this
     * loader receives the resolved product ID instead of the product entity. It loads the
     * required product fields by ID and adapts the PartialEntity for ProductConfiguratorLoader
     * to avoid a full product load and its regular loaded events.
     */
    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $productId = $inputs->stringOrNull('productId');
        if ($productId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = (new Criteria([$productId]))->addFields([
            'id',
            'parentId',
            'optionIds',
            'variantListingConfig',
        ]);

        /** @var PartialEntity|null $entity */
        $entity = $this->productRepository->search($criteria, $context)->getEntities()->first();
        if (!$entity instanceof PartialEntity) {
            return ContentDataLoaderResult::notFound();
        }

        /** @var ?string $parentId */
        $parentId = $entity->get('parentId');
        /** @var ?array<string> $optionIds */
        $optionIds = $entity->get('optionIds');
        /** @var ?VariantListingConfig $variantListingConfig */
        $variantListingConfig = $entity->get('variantListingConfig');

        $parentId ??= $entity->getId();
        $productData = new ProductConfiguratorProductData(
            $entity->getId(),
            $parentId,
            $optionIds,
            $variantListingConfig,
        );

        return ContentDataLoaderResult::cached(
            $this->configuratorLoader->load($productData, $context),
            EntityCacheKeyGenerator::buildProductTag($parentId)
        );
    }
}
