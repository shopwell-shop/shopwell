<?php

declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopwell\Core\Framework\Log\Package;

/**
 * @implements Rule<String_>
 *
 * @internal
 */
#[Package('framework')]
class StorefrontRouteUsageRule implements Rule
{
    /**
     * @var list<string>
     *
     * @phpstan-ignore shopwell.storefrontRouteUsage, shopwell.storefrontRouteUsage (As the PHPStan rule checks itself, this needs to be ignored)
     */
    private const NOT_ALLOWED_STOREFRONT_ROUTE_PREFIXES = ['frontend.', 'widgets.'];

    /**
     * @var list<string>
     */
    private array $allowedStorefrontRouteNamespaces;

    public function __construct(
        private readonly Configuration $configuration,
    ) {
        // see src/Core/DevOps/StaticAnalyze/PHPStan/extension.neon for the default config
        $this->allowedStorefrontRouteNamespaces = $this->configuration->getAllowedStorefrontRouteNamespaces();
    }

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $scopeClassReflection = $scope->getClassReflection();
        if (!$scopeClassReflection || TestRuleHelper::isTestClass($scopeClassReflection)) {
            return [];
        }

        $namespace = $scope->getNamespace();
        if ($namespace === null) {
            return [];
        }

        foreach ($this->allowedStorefrontRouteNamespaces as $allowedStorefrontRouteNamespace) {
            if (str_starts_with($namespace, $allowedStorefrontRouteNamespace)) {
                return [];
            }
        }

        $value = $node->value;
        foreach (self::NOT_ALLOWED_STOREFRONT_ROUTE_PREFIXES as $notAllowedStorefrontRoutesPrefix) {
            if (str_starts_with($value, $notAllowedStorefrontRoutesPrefix)) {
                $message = \sprintf(
                    'Using a route name starting with "%s" is not allowed in the "%s" namespace (found: "%s").',
                    $notAllowedStorefrontRoutesPrefix,
                    $namespace,
                    $value
                );

                return [
                    RuleErrorBuilder::message($message)
                        ->line($node->getStartLine())
                        ->identifier('shopwell.storefrontRouteUsage')
                        ->tip(\sprintf('Routes starting with "%s" are provided by the Storefront package, which is not always installed.', $notAllowedStorefrontRoutesPrefix))
                        ->build(),
                ];
            }
        }

        return [];
    }
}
