<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\StoreException;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ExtensionUpdateRequiresConsentAffirmationException extends StoreException
{
}
