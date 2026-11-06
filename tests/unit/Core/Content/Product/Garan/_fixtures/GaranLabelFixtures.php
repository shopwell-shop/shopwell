<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Garan\_fixtures;

use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopwell\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopwell\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopwell\Core\Content\Product\Garan\GaranLabelResolver;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
final class GaranLabelFixtures
{
    /**
     * The label values {@see self::assignLabel()} gives a product
     */
    public const PRODUCT_LABEL = ['guaranteeMonths' => 36, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    /**
     * Label values a line item stored earlier, different from {@see self::PRODUCT_LABEL}
     */
    public const STORED_LABEL = ['guaranteeMonths' => 42, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    public static function createResolver(): GaranLabelResolver
    {
        // resolving the label values never renders a template
        return new GaranLabelResolver(
            new GaranLabelDurationFormatter(),
            new GaranLabelRenderer(new Environment(new ArrayLoader())),
        );
    }

    public static function createProduct(string $id): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId($id);
        self::assignLabel($product);

        return $product;
    }

    public static function assignLabel(ProductEntity $product): void
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setTranslated(['name' => 'ACME']);

        $product->setManufacturer($manufacturer);
        $product->setManufacturerNumber('ACME-36');
        $product->setGuaranteeMonths(36);
        $product->setGuaranteeConfirmed(true);
    }
}
