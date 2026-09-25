<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Authorization\Subscription;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum SubscriberType
{
    case App;
    case Admin;
    case User;
    case Integration;
    case AppIntegration;
    case None;
}
