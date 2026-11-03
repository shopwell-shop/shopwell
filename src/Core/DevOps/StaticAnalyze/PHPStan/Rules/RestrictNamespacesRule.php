<?php

declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class RestrictNamespacesRule
{
    private const NAMESPACE_ADMINISTRATION = 'Shopwell\Administration';
    private const NAMESPACE_CHECKOUT_PAYMENT = 'Shopwell\Core\Checkout\Payment';
    private const NAMESPACE_CORE = 'Shopwell\Core';
    private const NAMESPACE_ELASTICSEARCH = 'Shopwell\Elasticsearch';
    private const NAMESPACE_STOREFRONT = 'Shopwell\Storefront';
    private const NAMESPACE_SYSTEM_DEPENDENCY_INJECTION = 'Shopwell\Core\System\DependencyInjection';
    private const NAMESPACE_SYSTEM_PAYMENT = 'Shopwell\Core\System\Payment';

    #[TestRule]
    public function restrictNamespacesInAdministration(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ADMINISTRATION))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
                Selector::inNamespace(self::NAMESPACE_STOREFRONT),
            );
    }

    #[TestRule]
    public function restrictCheckoutPaymentDependenciesInSystem(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_SYSTEM_PAYMENT),
                Selector::inNamespace(self::NAMESPACE_SYSTEM_DEPENDENCY_INJECTION),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace(self::NAMESPACE_CHECKOUT_PAYMENT));
    }

    #[TestRule]
    public function restrictNamespacesInCore(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_CORE))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
                Selector::inNamespace(self::NAMESPACE_STOREFRONT),
            );
    }

    #[TestRule]
    public function restrictNamespacesInElasticsearch(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_STOREFRONT),
            );
    }

    #[TestRule]
    public function restrictNamespacesInStorefront(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_STOREFRONT))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
            );
    }
}
