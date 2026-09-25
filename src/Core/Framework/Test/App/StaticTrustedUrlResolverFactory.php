<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\App;

use Shopwell\Core\Content\Media\File\TrustedUrlResolver;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class StaticTrustedUrlResolverFactory
{
    /**
     * @param list<string> $allowedPrivateIpAddresses
     */
    public static function create(array $allowedPrivateIpAddresses): TrustedUrlResolver
    {
        return new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34'], allowedPrivateIps: $allowedPrivateIpAddresses);
    }
}
