<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;

/**
 * An api that does nothing - the tests only need a class name the declaration may point at.
 *
 * @internal
 */
#[Package('framework')]
final class TestPaymentApi extends AbstractPaymentApi
{
}
