<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;

/**
 * A second base implementation of the very same technical name - what the compiler pass has to reject,
 * because nothing would decide which one an app is charged through.
 *
 * @internal
 */
#[Package('framework')]
final class TestPaymentProviderDuplicate extends TestPaymentProvider
{
}
