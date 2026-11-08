<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('fundamentals@after-sales')]
class ProductCrossSellingSerializer extends EntitySerializer
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductCrossSellingAssignedProductsCollection> $assignedProductsRepository
     */
    public function __construct(private readonly EntityRepository $assignedProductsRepository)
    {
    }

    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        yield from parent::serialize($config, $definition, $entity);

        if (!isset($entity['assignedProducts'])) {
            return;
        }

        $assignedProducts = $entity['assignedProducts'];
        if ($assignedProducts instanceof Struct) {
            $assignedProducts = $assignedProducts->jsonSerialize();
        }

        $productIds = [];

        foreach ($assignedProducts as $assignedProduct) {
            $assignedProduct = $assignedProduct instanceof ProductCrossSellingAssignedProductsEntity
                ? $assignedProduct->jsonSerialize()
                : $assignedProduct;
            $productIds[$assignedProduct['position']] = $assignedProduct['productId'];
        }

        ksort($productIds);

        $result = implode('|', $productIds);

        yield 'assignedProducts' => $result;
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);
        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        $productIds = $deserialized['assignedProducts'] ?? null;

        if (!\is_array($productIds) || $productIds === []) {
            return $deserialized;
        }

        $crossSellingId = $deserialized['id'] ?? null;
        $assignedProducts = [];

        foreach ($productIds as $position => $productId) {
            $assignedProduct = [
                'productId' => $productId,
                'crossSellingId' => $crossSellingId,
                'position' => $position,
            ];

            $assignedProducts[] = $assignedProduct;
        }

        if ($crossSellingId) {
            $assignedProducts = $this->findAssignedProductsIds($crossSellingId, $assignedProducts);
        }

        $deserialized['assignedProducts'] = $assignedProducts;

        return $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === ProductCrossSellingDefinition::ENTITY_NAME;
    }

    /**
     * @param list<array{productId: string, crossSellingId: string, position: int}> $assignedProducts
     *
     * @return array<array{productId: string, crossSellingId: string, position: int, id?: string}>
     */
    private function findAssignedProductsIds(string $crossSellingId, array $assignedProducts): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('crossSellingId', $crossSellingId));
        $criteria->addFilter(new EqualsAnyFilter('productId', array_column($assignedProducts, 'productId')));

        $existingIds = [];
        foreach ($this->assignedProductsRepository->search($criteria, Context::createDefaultContext())->getEntities() as $existing) {
            $existingIds[$existing->getProductId()] = $existing->getId();
        }

        foreach ($assignedProducts as $i => $assignedProduct) {
            if (isset($existingIds[$assignedProduct['productId']])) {
                $assignedProducts[$i]['id'] = $existingIds[$assignedProduct['productId']];
            }
        }

        return $assignedProducts;
    }
}
