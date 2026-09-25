<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Cart\CachedRuleLoader;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopwell\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopwell\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopwell\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopwell\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopwell\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopwell\Core\Content\Cms\CmsPageDefinition;
use Shopwell\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopwell\Core\Content\Media\Event\MediaIndexerEvent;
use Shopwell\Core\Content\Media\SalesChannel\MediaRoute;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingTranslation\ProductCrossSellingTranslationDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopwell\Core\Content\Product\Events\InvalidateProductCache;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopwell\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamWriteResultHelper;
use Shopwell\Core\Content\ProductStream\ProductStreamDefinition;
use Shopwell\Core\Content\Sitemap\Event\SitemapGeneratedEvent;
use Shopwell\Core\Content\Sitemap\SalesChannel\SitemapRoute;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopwell\Core\System\Country\CountryDefinition;
use Shopwell\Core\System\Country\SalesChannel\CountryRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopwell\Core\System\Currency\CurrencyDefinition;
use Shopwell\Core\System\Currency\SalesChannel\CurrencyRoute;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\Language\SalesChannel\LanguageRoute;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopwell\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopwell\Core\System\Salutation\SalutationDefinition;
use Shopwell\Core\System\Snippet\SnippetDefinition;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineDefinition;
use Shopwell\Core\System\SystemConfig\CachedSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\Event\SystemConfigChangedHook;
use Shopwell\Core\System\Tax\TaxDefinition;

#[Package('framework')]
/**
 * @internal
 */
class CacheInvalidationSubscriber
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
        private readonly bool $productStreamIndexerEnabled,
    ) {
    }

    public function invalidateInitialStateIdLoader(EntityWrittenContainerEvent $event): void
    {
        if (!$event->getPrimaryKeys(StateMachineDefinition::ENTITY_NAME)) {
            return;
        }

        $this->cacheInvalidator->invalidate([InitialStateIdLoader::CACHE_KEY], true);
    }

    public function invalidateSitemap(SitemapGeneratedEvent $event): void
    {
        $this->cacheInvalidator->invalidate([
            SitemapRoute::buildName($event->getSalesChannelContext()->getSalesChannelId()),
        ]);
    }

    public function invalidateConfig(): void
    {
        // invalidates the complete cached config immediately
        $this->cacheInvalidator->invalidate([CachedSystemConfigLoader::CACHE_TAG], true);
    }

    public function invalidateConfigKey(SystemConfigChangedHook $event): void
    {
        // invalidates the complete cached config immediately
        $this->cacheInvalidator->invalidate([CachedSystemConfigLoader::CACHE_TAG], true);

        // SystemConfig is often uses to store internal values. In such cases clients can set silent flag to true,
        // so no http cache invalidations will happen.
        if ($event->silent) {
            return;
        }

        // global system config tag is used in all http caches that access system config, that should be invalidated delayed
        $this->cacheInvalidator->invalidate(['system.config-' . $event->salesChannelId]);
    }

    public function invalidateSnippets(EntityWrittenContainerEvent $event): void
    {
        // invalidates all http cache items where the snippets used
        $snippets = $event->getEventByEntityName(SnippetDefinition::ENTITY_NAME);

        if (!$snippets) {
            return;
        }

        $setIds = $this->getSetIds($snippets->getIds());

        if ($setIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(Translator::tag(...), $setIds));
    }

    public function invalidateShippingMethodRoute(EntityWrittenContainerEvent $event): void
    {
        // checks if a shipping method changed or the assignment between shipping method and sales channel
        $logs = [...$this->getChangedShippingMethods($event), ...$this->getChangedShippingAssignments($event)];

        $this->cacheInvalidator->invalidate($logs);
    }

    public function invalidateRules(): void
    {
        // immediately invalidates the rule loader each time a rule changed or a plugin install state changed
        $this->cacheInvalidator->invalidate([CachedRuleLoader::CACHE_KEY], true);
    }

    public function invalidateCmsPageIds(EntityWrittenContainerEvent $event): void
    {
        // invalidates all routes and http cache pages where a cms page was loaded, the id is assigned as tag
        $ids = array_map(EntityCacheKeyGenerator::buildCmsTag(...), $event->getPrimaryKeys(CmsPageDefinition::ENTITY_NAME));
        $this->cacheInvalidator->invalidate($ids);
    }

    public function invalidateCategoryRouteByCategoryTranslationChanges(EntityWrittenContainerEvent $event): void
    {
        $changedCategoryTranslations = $event->getPrimaryKeysWithPropertyChange(
            CategoryTranslationDefinition::ENTITY_NAME,
            ['slotConfig']
        );

        if ($changedCategoryTranslations === []) {
            return;
        }

        /** @var array<string, true> $categoryIds */
        $categoryIds = [];
        foreach ($changedCategoryTranslations as $primaryKey) {
            if (isset($primaryKey['categoryId']) && \is_string($primaryKey['categoryId'])) {
                $categoryIds[$primaryKey['categoryId']] = true;
            }
        }

        if ($categoryIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(CategoryRoute::buildName(...), array_keys($categoryIds)));
    }

    public function invalidateProduct(InvalidateProductCache $event): void
    {
        $listing = array_map(ProductListingRoute::buildName(...), $this->getProductCategoryIds($event->getIds()));

        $parents = array_map(ProductDetailRoute::buildName(...), $this->getParentIds($event->getIds()));

        $streams = array_map(EntityCacheKeyGenerator::buildStreamTag(...), $this->getStreamIds($event->getIds()));

        $tags = array_merge($listing, $parents, $streams);

        $this->cacheInvalidator->invalidate($tags, force: $event->force);
    }

    public function invalidateProductCrossSelling(EntityWrittenContainerEvent $event): void
    {
        $productIds = $this->getChangedCrossSellingProductIds($event);

        if ($productIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(ProductDetailRoute::buildName(...), $productIds), true);
    }

    public function invalidateProductCrossSellingBeforeDeletion(EntityDeleteEvent $event): void
    {
        $productIds = $this->getDeletedCrossSellingProductIds($event);

        if ($productIds === []) {
            return;
        }

        $event->addSuccess(
            fn () => $this->cacheInvalidator->invalidate(array_map(ProductDetailRoute::buildName(...), $productIds), true)
        );
    }

    public function invalidateStreamIds(EntityWrittenContainerEvent $event): void
    {
        // invalidates all routes which are loaded based on a stream (e.G. category listing and cross selling)
        $streamIds = [
            ...$event->getPrimaryKeys(ProductStreamDefinition::ENTITY_NAME),
            ...ProductStreamWriteResultHelper::getAffectedStreamIds($event),
        ];

        $streamIds = array_values(array_unique($streamIds));

        if ($streamIds === []) {
            return;
        }

        $ids = array_map(EntityCacheKeyGenerator::buildStreamTag(...), $streamIds);
        $this->cacheInvalidator->invalidate($ids, force: true);
    }

    public function invalidateCategoryRouteByCategoryIds(CategoryIndexerEvent $event): void
    {
        // invalidates the category route cache when a category changed
        $this->cacheInvalidator->invalidate(array_map(CategoryRoute::buildName(...), $event->getIds()));
    }

    public function invalidateIndexedLandingPages(LandingPageIndexerEvent $event): void
    {
        // invalidates the landing page route, if the corresponding landing page changed
        $ids = array_map(LandingPageRoute::buildName(...), $event->getIds());
        $this->cacheInvalidator->invalidate($ids);
    }

    public function invalidateCurrencyRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the currency route when a currency changed or an assignment between the sales channel and currency changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedCurrencyAssignments($event),
            ...$this->getChangedCurrencies($event),
        ]);
    }

    public function invalidateLanguageRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the language route when a language changed or an assignment between the sales channel and language changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedLanguageAssignments($event),
            ...$this->getChangedLanguages($event),
        ]);
    }

    public function invalidateCountryRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the country route when a country changed or an assignment between the sales channel and country changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedCountryAssignments($event),
            ...$this->getChangedCountries($event),
        ]);
    }

    public function invalidateCountryStateRoute(EntityWrittenContainerEvent $event): void
    {
        $tags = [];
        if (
            $event->getDeletedPrimaryKeys(CountryStateDefinition::ENTITY_NAME)
            || $event->getPrimaryKeysWithPropertyChange(CountryStateDefinition::ENTITY_NAME, ['countryId'])
        ) {
            $tags[] = CountryStateRoute::ALL_TAG;
        }

        if ($tags === []) {
            // invalidates the country-state route when a state changed or an assignment between the state and country changed
            $tags = array_map(
                CountryStateRoute::buildName(...),
                $event->getPrimaryKeys(CountryDefinition::ENTITY_NAME)
            );
        }

        $this->cacheInvalidator->invalidate($tags);
    }

    public function invalidateSalutationRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the salutation route when a salutation changed
        $this->cacheInvalidator->invalidate([...$this->getChangedSalutations($event)]);
    }

    public function invalidateNavigationRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the navigation route when a category changed or the entry point configuration of an sales channel changed
        $changedSalesChannelSettings = $event->getPrimaryKeysWithPropertyChange(
            SalesChannelDefinition::ENTITY_NAME,
            ['navigationCategoryId', 'navigationCategoryDepth', 'serviceCategoryId', 'footerCategoryId']
        );
        if ($changedSalesChannelSettings !== []) {
            // if the sales channel settings changed, we invalidate the complete navigation route
            $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG]);

            return;
        }

        $changedCategoryData = $event->getPrimaryKeysWithPropertyChange(
            CategoryDefinition::ENTITY_NAME,
            ['parentId', 'afterCategoryId', 'visible', 'active']
        );
        if ($changedCategoryData !== []) {
            // if category data that has impact on navigation changes, we invalidate the complete navigation route
            $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG]);

            return;
        }

        $deletedCategories = $event->getDeletedPrimaryKeys(CategoryDefinition::ENTITY_NAME);
        if ($deletedCategories !== []) {
            // if the category is deleted, we invalidate the complete navigation route
            $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG]);

            return;
        }

        $changedCategoryTranslationData = $event->getPrimaryKeysWithPropertyChange(
            CategoryTranslationDefinition::ENTITY_NAME,
            ['name']
        );
        if ($changedCategoryTranslationData !== []) {
            // if translated category data that has impact on navigation changes, we invalidate the complete navigation route
            $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG]);
        }
    }

    public function invalidatePaymentMethodRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the payment method route when a payment method changed or an assignment between the sales channel and payment method changed
        $logs = [...$this->getChangedPaymentMethods($event), ...$this->getChangedPaymentAssignments($event)];

        $this->cacheInvalidator->invalidate($logs);
    }

    public function invalidateMedia(MediaIndexerEvent $event): void
    {
        /** @var list<array{'product_id':string, 'variant_id':string|null}> $productIds */
        $productIds = $this->connection->fetchAllAssociative(
            'SELECT
                    LOWER(HEX(pm.product_id)) as product_id,
                    IF(variant.id IS NULL,  NULL, LOWER(HEX(variant.id))) as variant_id
                    FROM product_media AS pm
                    LEFT JOIN product as variant ON (pm.product_id = variant.parent_id)
                    WHERE media_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($event->getIds())],
            ['ids' => ArrayParameterType::STRING]
        );

        $variantIds = array_filter(array_column($productIds, 'variant_id'));
        $uniqueProductIds = array_unique(array_column($productIds, 'product_id'));
        $productIds = array_merge(
            $uniqueProductIds,
            $variantIds,
        );

        $tags = array_map(ProductDetailRoute::buildName(...), $productIds);

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('CACHE_REWORK')) {
            $tags = array_merge($tags, array_map(MediaRoute::buildName(...), $event->getIds()));
        }

        $this->cacheInvalidator->invalidate($tags);
    }

    public function invalidateContext(EntityWrittenContainerEvent $event): void
    {
        // invalidates the context cache - each time one of the entities which are considered inside the context factory changed
        $ids = $event->getPrimaryKeys(SalesChannelDefinition::ENTITY_NAME);
        $keys = array_map(CachedSalesChannelContextFactory::buildName(...), $ids);
        $keys = array_merge($keys, array_map(CachedBaseSalesChannelContextFactory::buildName(...), $ids));

        if ($event->getEventByEntityName(CurrencyDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(PaymentMethodDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(ShippingMethodDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(TaxDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(CountryDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(CustomerGroupDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(LanguageDefinition::ENTITY_NAME)) {
            $keys[] = CachedSalesChannelContextFactory::ALL_TAG;
        }

        $keys = array_filter(array_unique($keys));

        if ($keys === []) {
            return;
        }

        // immediately invalidates the context cache
        $this->cacheInvalidator->invalidate($keys, true);
    }

    public function invalidateManufacturerFilters(EntityWrittenContainerEvent $event): void
    {
        // invalidates the product listing route, each time a manufacturer changed
        $ids = $event->getPrimaryKeys(ProductManufacturerDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
                INNER JOIN product ON product.id = product_category_tree.product_id AND product_category_tree.product_version_id = product.version_id
             WHERE product.product_manufacturer_id IN (:ids)
             AND product.version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->cacheInvalidator->invalidate(
            array_map(ProductListingRoute::buildName(...), $ids)
        );
    }

    public function invalidatePropertyFilters(EntityWrittenContainerEvent $event): void
    {
        $this->cacheInvalidator->invalidate($this->getDeletedPropertyFilterTags($event));
    }

    public function invalidateStreamsBeforeIndexing(EntityWrittenContainerEvent $event): void
    {
        // invalidates all stream based pages and routes before the product indexer changes product_stream_mapping
        $ids = $event->getPrimaryKeys(ProductDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        // invalidates product listings which are based on a product stream
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_stream_id))
             FROM product_stream_mapping
             WHERE product_stream_mapping.product_id IN (:ids)
             AND product_stream_mapping.product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->cacheInvalidator->invalidate(
            array_map(EntityCacheKeyGenerator::buildStreamTag(...), $ids)
        );
    }

    /**
     * @return string[]
     */
    private function getDeletedPropertyFilterTags(EntityWrittenContainerEvent $event): array
    {
        // invalidates the product listing route, each time a property changed
        $ids = $event->getDeletedPrimaryKeys(ProductPropertyDefinition::ENTITY_NAME);

        if ($ids === []) {
            return [];
        }

        $productIds = array_column($ids, 'productId');

        return array_merge(
            array_map(ProductDetailRoute::buildName(...), array_unique($productIds)),
            array_map(ProductListingRoute::buildName(...), $this->getProductCategoryIds($productIds))
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getProductCategoryIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM product_category_tree
             WHERE product_id IN (:ids)
             AND product_version_id = :version
             AND category_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @return list<string>
     */
    private function getChangedShippingMethods(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(ShippingMethodDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_shipping_method WHERE shipping_method_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(ShippingMethodDefinition::ENTITY_NAME)) {
            $tags[] = ShippingMethodRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(ShippingMethodRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedShippingAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the shipping assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelShippingMethodDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(ShippingMethodRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedPaymentMethods(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(PaymentMethodDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_payment_method WHERE payment_method_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(PaymentMethodDefinition::ENTITY_NAME)) {
            $tags[] = PaymentMethodRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(PaymentMethodRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedPaymentAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the language assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelPaymentMethodDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(PaymentMethodRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedCountries(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(CountryDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        // Used to detect changes to the country itself and invalidate the route for all sales channels in which the country is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_country WHERE country_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(CountryDefinition::ENTITY_NAME)) {
            $tags[] = CountryRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(CountryRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedCountryAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the country assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelCountryDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(CountryRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedSalutations(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(SalutationDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        return [SalutationRoute::buildName()];
    }

    /**
     * @return list<string>
     */
    private function getChangedLanguages(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(LanguageDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        // Used to detect changes to the language itself and invalidate the route for all sales channels in which the language is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_language WHERE language_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(LanguageDefinition::ENTITY_NAME)) {
            $tags[] = LanguageRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(LanguageRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedLanguageAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the language assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelLanguageDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(LanguageRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedCurrencies(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(CurrencyDefinition::ENTITY_NAME);

        if ($ids === []) {
            return [];
        }

        // Used to detect changes to the currency itself and invalidate the route for all sales channels in which the currency is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_currency WHERE currency_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(CurrencyDefinition::ENTITY_NAME)) {
            $tags[] = CurrencyRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(CurrencyRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedCurrencyAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the currency assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelCurrencyDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map(CurrencyRoute::buildName(...), $ids);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getParentIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(parent_id, id))) as id FROM product WHERE id IN (:ids) AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getStreamIds(array $ids): array
    {
        if (!$this->productStreamIndexerEnabled) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_stream_id))
             FROM product_stream_mapping
             WHERE product_stream_mapping.product_id IN (:ids)
             AND product_stream_mapping.product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @return list<string>
     */
    private function getChangedCrossSellingProductIds(EntityWrittenContainerEvent $event): array
    {
        /** @var list<string> $crossSellingIds */
        $crossSellingIds = array_values([
            ...$event->getPrimaryKeys(ProductCrossSellingDefinition::ENTITY_NAME),
            ...$this->getCrossSellingIdsFromAssignedProductWrites($event->getEventByEntityName(ProductCrossSellingAssignedProductsDefinition::ENTITY_NAME)),
            ...$this->getCrossSellingIdsByAssignedProductIds($event->getPrimaryKeys(ProductCrossSellingAssignedProductsDefinition::ENTITY_NAME)),
            ...$this->getCrossSellingIdsFromTranslationPrimaryKeys($event->getPrimaryKeys(ProductCrossSellingTranslationDefinition::ENTITY_NAME)),
        ]);

        return $this->getProductIdsByCrossSellingIds($crossSellingIds);
    }

    /**
     * @return list<string>
     */
    private function getDeletedCrossSellingProductIds(EntityDeleteEvent $event): array
    {
        /** @var list<string> $assignedProductIds */
        $assignedProductIds = array_values($event->getIds(ProductCrossSellingAssignedProductsDefinition::ENTITY_NAME));

        /** @var list<string> $crossSellingIds */
        $crossSellingIds = array_values([
            ...$event->getIds(ProductCrossSellingDefinition::ENTITY_NAME),
            ...$this->getCrossSellingIdsByAssignedProductIds($assignedProductIds),
            ...$this->getCrossSellingIdsFromTranslationPrimaryKeys(array_values($event->getIds(ProductCrossSellingTranslationDefinition::ENTITY_NAME))),
        ]);

        return $this->getProductIdsByCrossSellingIds($crossSellingIds);
    }

    /**
     * @param list<string|array<string, string>> $ids
     *
     * @return list<string>
     */
    private function getCrossSellingIdsFromTranslationPrimaryKeys(array $ids): array
    {
        $crossSellingIds = [];

        foreach ($ids as $id) {
            if (!\is_array($id)) {
                continue;
            }

            $crossSellingId = $id['productCrossSellingId'] ?? $id['product_cross_selling_id'] ?? null;

            if (!\is_string($crossSellingId)) {
                continue;
            }

            $crossSellingIds[] = Uuid::isValid($crossSellingId) ? $crossSellingId : Uuid::fromBytesToHex($crossSellingId);
        }

        return array_values(array_unique($crossSellingIds));
    }

    /**
     * @return list<string>
     */
    private function getCrossSellingIdsFromAssignedProductWrites(?EntityWrittenEvent $event): array
    {
        if ($event === null) {
            return [];
        }

        $crossSellingIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            $payloadId = $payload['crossSellingId'] ?? $payload['cross_selling_id'] ?? null;

            if (\is_string($payloadId)) {
                $crossSellingIds[] = Uuid::isValid($payloadId) ? $payloadId : Uuid::fromBytesToHex($payloadId);
            }

            $state = $writeResult->getExistence()?->getState() ?? [];
            $stateId = $state['crossSellingId'] ?? $state['cross_selling_id'] ?? null;

            if (\is_string($stateId)) {
                $crossSellingIds[] = Uuid::isValid($stateId) ? $stateId : Uuid::fromBytesToHex($stateId);
            }
        }

        return array_values(array_unique($crossSellingIds));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getCrossSellingIdsByAssignedProductIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(cross_selling_id))
             FROM product_cross_selling_assigned_products
             WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getProductIdsByCrossSellingIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(product.parent_id, product.id)))
             FROM product_cross_selling
             INNER JOIN product
                ON product.id = product_cross_selling.product_id
                AND product.version_id = product_cross_selling.product_version_id
             WHERE product_cross_selling.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getSetIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(snippet_set_id)) FROM snippet WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
