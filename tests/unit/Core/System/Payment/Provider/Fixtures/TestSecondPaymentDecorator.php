<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;

/**
 * The same decorator as {@see TestPaymentDecorator}, under a name of its own.
 *
 * A chain of three steps needs three classes: the chain parameter stores class names, so two steps of
 * the same class would be indistinguishable in it and the order of the chain could not be asserted.
 * The behaviour is inherited on purpose - only the identity differs.
 *
 * @internal
 */
#[Package('framework')]
class TestSecondPaymentDecorator extends TestPaymentDecorator
{
}
