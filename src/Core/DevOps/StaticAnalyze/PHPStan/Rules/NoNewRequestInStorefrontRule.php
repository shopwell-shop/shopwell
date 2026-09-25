<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @implements Rule<New_>
 */
#[Package('framework')]
class NoNewRequestInStorefrontRule implements Rule
{
    private const SHOPWELL_STOREFRONT_CONTROLLER = 'Shopwell\\Storefront\\Controller';

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        if ($node->class instanceof Name && $node->class->toString() === Request::class) {
            $classReflection = $scope->getClassReflection();
            if ($classReflection !== null && str_contains($classReflection->getName(), self::SHOPWELL_STOREFRONT_CONTROLLER)) {
                return [
                    RuleErrorBuilder::message('Do not create new Request objects in storefront/controller namespace, because not all parameters might be available on the new request, leading to errors further down. Consider cloning the original request or use a different approach.')
                    ->identifier('shopwell.noNewRequestInStorefront')
                    ->build(),
                ];
            }
        }

        return [];
    }
}
