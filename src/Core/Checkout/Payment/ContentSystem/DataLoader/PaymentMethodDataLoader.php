<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\ContentSystem\DataLoader;

use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
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
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available payment methods via AbstractPaymentMethodRoute.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<PaymentMethodCollection>
 */
#[Package('framework')]
class PaymentMethodDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'payment_method';

    public function __construct(
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
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
            new ConfigKeySpecification('onlyAvailable', ConfigKeyKind::Literal, 'boolean', required: false, hasDefault: true, default: true),
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

        // Clone request to set onlyAvailable parameter
        $clonedRequest = clone $request;
        $clonedRequest->query->set('onlyAvailable', $inputs->bool('onlyAvailable'));

        // Any ShopwellHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: PaymentMethodRoute runs the PaymentMethodRouteHook app scripts through
        // ScriptExecutor, which rewraps any Throwable they raise as ScriptExecutionFailedException.
        try {
            $response = $this->paymentMethodRoute->load($clonedRequest, $context, $criteria);
        } catch (ShopwellHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // PaymentMethodRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getPaymentMethods());
    }
}
