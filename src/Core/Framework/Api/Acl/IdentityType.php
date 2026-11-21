<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Acl;

use Shopwell\Core\Framework\Log\Package;

/**
 * Who acts: a user, an integration, an app through its manifest, or an app through its integration.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum IdentityType: string
{
    case User = 'user';
    case Integration = 'integration';
    case App = 'app';
    case AppIntegration = 'app_integration';
}
