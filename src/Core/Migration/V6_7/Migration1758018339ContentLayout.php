<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1758018339ContentLayout extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1758018339;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `content_layout` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `version` VARCHAR(20) NOT NULL,
                `layout` JSON NOT NULL,
                `root_source` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `uniq.content_layout.name_version` (`name`, `version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $connection->executeStatement($sql);
        $this->seedLayouts($connection);
    }

    private function seedLayouts(Connection $connection): void
    {
        $productContext = ['product' => ['type' => 'single', 'required' => false, 'scope' => 'root', 'source' => ['type' => 'root', 'id' => 'product']]];
        $listingContext = ['productListing' => ['type' => 'single', 'required' => false, 'scope' => 'root']];
        $layouts = [
            'category' => ['name' => 'Default category', 'root' => 'category', 'key' => 'core.content_system.default_category_content_layout', 'elements' => [
                $this->element('category-title', 'Sw:Content:Text', ['text' => '<h1>{{map:category.name}}</h1>']),
                $this->element('category-filters', 'Sw:Filter:Panel', [], $listingContext),
                $this->element('category-listing', 'Sw:Product:Listing', [], ['productListing' => [...$listingContext['productListing'], 'propertyAlias' => 'listing']]),
            ]],
            'landing' => ['name' => 'Default landing page', 'root' => 'landing_page', 'key' => 'core.content_system.default_landing_page_content_layout', 'elements' => [$this->element('landing-title', 'Sw:Content:Text', ['text' => '<h1>{{map:landing_page.name}}</h1>'])]],
            'product' => ['name' => 'Default product', 'root' => 'product', 'key' => 'core.content_system.default_product_content_layout', 'elements' => [
                $this->element('product-name', 'Sw:Content:Text', ['text' => '<h1>{{map:product.name}}</h1>']),
                $this->element('product-gallery', 'Sw:Media:Gallery', [], ['mediaItems' => ['type' => 'collection', 'required' => false, 'scope' => 'root', 'source' => ['type' => 'root', 'id' => 'product', 'path' => 'media']]]),
                $this->element('product-manufacturer', 'Sw:Product:Manufacturer', [], $productContext),
                $this->element('product-variants', 'Sw:Product:VariantSelection', [], $productContext),
                $this->element('product-price', 'Sw:Product:PriceDisplay', [], $productContext),
                $this->element('product-buy', 'Sw:Product:BuyButton', ['showQuantitySelect' => true], $productContext),
                $this->element('product-guarantee', 'Sw:Product:GaranLabel', [], $productContext),
                $this->element('product-description', 'Sw:Content:Text', ['text' => '{{map:product.description}}']),
                $this->element('product-reviews', 'Sw:Product:Reviews'),
                $this->element('product-review-form', 'Sw:Product:ReviewForm'),
            ]],
        ];
        foreach (['tosPage' => 'Terms and conditions', 'revocationPage' => 'Revocation', 'shippingPaymentInfoPage' => 'Shipping and payment', 'privacyPage' => 'Privacy', 'imprintPage' => 'Imprint', 'http404Page' => 'Page not found', 'maintenancePage' => 'Maintenance'] as $key => $title) {
            $layouts[$key] = ['name' => $title, 'root' => 'none', 'key' => 'core.basicInformation.' . $key, 'elements' => [$this->element($key, 'Sw:Content:Text', ['text' => '<h1>' . $title . '</h1>'])]];
        }
        $layouts['http404Page']['elements'] = [$this->element('http404Page', 'Sw:Content:PageNotFound')];
        foreach (['contactPage' => 'Contact', 'newsletterPage' => 'Newsletter', 'revocationRequestPage' => 'RevocationRequest'] as $key => $component) {
            $element = $this->element($key, 'Sw:Form:' . $component);
            $element['dataRequirements'] = ['genders' => ['key' => 'genders', 'source' => 'form_genders', 'config' => []]];
            $layouts[$key] = ['name' => $component . ' form', 'root' => 'none', 'key' => 'core.basicInformation.' . $key, 'elements' => [$element]];
        }
        foreach ($layouts as $key => $layout) {
            $id = Hasher::hash('shopwell.content-system.default.' . $key, 'md5');
            $connection->executeStatement('INSERT IGNORE INTO content_layout (id, name, version, layout, root_source, created_at) VALUES (:id, :name, :version, :layout, :root, :created)', [
                'id' => Uuid::fromHexToBytes($id), 'name' => $layout['name'], 'version' => '1.0.0',
                'layout' => json_encode($layout['elements'], \JSON_THROW_ON_ERROR), 'root' => $layout['root'], 'created' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
            $this->setDefaultConfig($connection, $layout['key'], $id);
        }
    }

    private function setDefaultConfig(Connection $connection, string $key, string $id): void
    {
        if (!$connection->fetchOne('SELECT 1 FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL', ['key' => $key])) {
            $connection->insert('system_config', ['id' => Uuid::randomBytes(), 'configuration_key' => $key, 'configuration_value' => json_encode(['_value' => $id], \JSON_THROW_ON_ERROR), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        }
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function element(string $key, string $component, array $properties = [], array $context = []): array
    {
        $element = ['id' => Hasher::hash('shopwell.content-system.element.' . $key, 'md5'), 'component' => $component, 'properties' => $properties];
        if ($context !== []) {
            $element['acceptsContext'] = $context;
        }

        return $element;
    }
}
