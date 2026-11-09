<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency\ContentSystem\DataLoader;

use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available currencies via AbstractCurrencyRoute.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<CurrencyCollection>
 */
#[Package('framework')]
class CurrencyDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'currency';

    public function __construct(
        private readonly AbstractCurrencyRoute $currencyRoute,
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $criteria = new Criteria();

        foreach ($inputs->stringList('associations') as $association) {
            $criteria->addAssociation($association);
        }

        // Any ShopwellHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // No domain exception is reachable through this chain today (CurrencyRoute::load() collects a cache
        // tag and runs one sales-channel repository search); the wrap is uniform across the loaders because
        // the reachable set is open.
        try {
            $response = $this->currencyRoute->load($request, $context, $criteria);
        } catch (ShopwellHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // CurrencyRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getCurrencies());
    }
}
