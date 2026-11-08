<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event;

use Shopwell\Core\Framework\Log\Package;

/**
 * Marks events whose emails must use metadata-only logging.
 * Subclasses retain this policy even without repeating the attribute.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class SensitiveMail
{
}
