<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\MyFakeNamespace;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @deprecated tag:v6.8.0 - Removed rule
 */
class RemovedRule extends Rule
{
    public function match(RuleScope $scope): bool
    {
        Feature::throwIfActive('v6.8.0.0', 'Removed rule');

        return false;
    }

    public function getConstraints(): array
    {
        Feature::throwIfActive('v6.8.0.0', 'Removed rule');

        return [];
    }

    public function getConfig(): ?RuleConfig
    {
        if (Feature::isActive('v6.8.0.0')) {
            return null;
        }

        return new RuleConfig();
    }
}

/**
 * @deprecated tag:v6.8.0 - Removed rule
 */
class WrongRuleConfig extends Rule
{
    public function getConfig(): ?RuleConfig
    {
        if (Feature::isActive('v6.8.0.0')) {
            return new RuleConfig();
        }

        return null;
    }

    public function match(RuleScope $scope): bool
    {
        Feature::throwIfActive('v6.8.0.0', 'Removed rule');

        return false;
    }

    public function getConstraints(): array
    {
        Feature::throwIfActive('v6.8.0.0', 'Removed rule');

        return [];
    }
}
