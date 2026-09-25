<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Manifest\Xml\Gateway;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContextGateway extends AbstractGateway
{
    final public const PERMISSION = 'context_gateway';
}
