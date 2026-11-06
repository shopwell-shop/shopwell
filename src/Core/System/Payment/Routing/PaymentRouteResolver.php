<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
class PaymentRouteResolver
{
    /**
     * @var array<string, PaymentRoutingPolicy>
     */
    private array $policies = [];

    /**
     * @param iterable<PaymentRoutingPolicy> $policies
     */
    public function __construct(
        private readonly PaymentProductRegistry $products,
        private readonly PaymentProductEligibility $eligibility,
        private readonly PaymentConfigResolver $config,
        iterable $policies,
    ) {
        foreach ($policies as $policy) {
            if (isset($this->policies[$policy->getName()])) {
                throw PaymentProviderException::invalidProviderDeclaration('Duplicate routing policy: ' . $policy->getName());
            }
            $this->policies[$policy->getName()] = $policy;
        }
    }

    /**
     * @param list<string> $allowedProducts empty considers the registered catalog; account authorization still applies
     * @param list<string> $allowedMethods
     *
     * @return list<PaymentRoute>
     */
    public function options(PaymentAppEntity $app, PaymentContext $context, array $allowedProducts = [], array $allowedMethods = [], string $policyName = 'priority'): array
    {
        $policy = $this->policies[$policyName] ?? throw PaymentProviderException::unknownRoutingPolicy($policyName);
        $methods = [];
        foreach ($allowedProducts === [] ? $this->products->names() : $allowedProducts as $name) {
            $product = $this->products->get($name);
            if ($allowedMethods !== [] && !\in_array($product->method, $allowedMethods, true)) {
                continue;
            }
            try {
                $candidates = $this->productRoutes($app, $product, $context, $policyName);
            } catch (PaymentProviderException $exception) {
                // Discovery skips unavailable alternatives; an explicit product request preserves its rejection.
                if ($exception->getStatusCode() >= 500) {
                    throw $exception;
                }
                continue;
            }
            foreach ($candidates as $candidate) {
                $methods[$product->method][] = $candidate;
            }
        }
        $routes = [];
        foreach ($methods as $candidates) {
            $route = $policy->choose($candidates, $context);
            if (!\in_array($route, $candidates, true)) {
                throw PaymentProviderException::invalidProviderDeclaration('A routing policy returned a route outside its authorized candidates.');
            }
            $routes[] = $route;
        }

        return $routes;
    }

    /**
     * @param list<string> $allowedProducts
     */
    public function resolveMethod(PaymentAppEntity $app, string $method, PaymentContext $context, array $allowedProducts = [], string $policyName = 'priority'): PaymentRoute
    {
        foreach ($this->options($app, $context, $allowedProducts, [$method], $policyName) as $route) {
            return $route;
        }
        throw PaymentProviderException::unknownMethod($method);
    }

    /**
     * @param list<string> $allowedProducts
     */
    public function resolveProduct(PaymentAppEntity $app, string $product, PaymentContext $context, array $allowedProducts = [], string $policyName = 'priority'): PaymentRoute
    {
        if ($allowedProducts !== [] && !\in_array($product, $allowedProducts, true)) {
            throw PaymentProviderException::unknownChannel($product);
        }
        $policy = $this->policies[$policyName] ?? throw PaymentProviderException::unknownRoutingPolicy($policyName);
        $candidates = $this->productRoutes($app, $this->products->get($product), $context, $policyName);
        if ($candidates === []) {
            throw PaymentProviderException::providerNotEnabled($this->products->get($product)->providerTechnicalName, $app->id);
        }
        $route = $policy->choose($candidates, $context);
        if (!\in_array($route, $candidates, true)) {
            throw PaymentProviderException::invalidProviderDeclaration('A routing policy returned a route outside its authorized candidates.');
        }

        return $route;
    }

    /**
     * @return list<PaymentRoute>
     */
    private function productRoutes(PaymentAppEntity $app, PaymentProduct $product, PaymentContext $context, string $policyName): array
    {
        if (!$product->supports(PaymentCapability::CAPTURE)) {
            throw PaymentProviderException::providerLacksCapability($product->providerTechnicalName, PaymentCapability::CAPTURE->value);
        }
        $this->eligibility->assertEligible($product, $context);
        $accounts = $this->config->resolveCandidates($app, $product->technicalName, $context->currency, $context->device, $context->runtimeParams[$product->technicalName] ?? [], $context->accountRuntimeParams);
        if ($accounts === []) {
            throw PaymentProviderException::providerNotEnabled($product->providerTechnicalName, $app->id);
        }
        $routes = [];
        foreach ($accounts as $account) {
            foreach ($this->eligibility->interactions($product, $context) as $interaction) {
                $config = new ResolvedPaymentConfig(
                    $account->provider,
                    $account->product,
                    $account->credentials,
                    $account->params,
                    $account->runtimeParams,
                    array_replace($account->settings, ['flow' => $interaction]),
                    $account->accountId,
                    $account->priority,
                    $account->accountFingerprint,
                );
                $routes[] = new PaymentRoute($config, $context, $interaction, $policyName);
            }
        }

        return $routes;
    }
}
