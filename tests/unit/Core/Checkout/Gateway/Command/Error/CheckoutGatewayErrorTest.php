<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Error\Error;
use Shopwell\Core\Checkout\Gateway\Error\CheckoutGatewayError;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutGatewayError::class)]
class CheckoutGatewayErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new CheckoutGatewayError('test', Error::LEVEL_NOTICE, true);

        static::assertSame('test', $error->getMessage());
        static::assertSame(Error::LEVEL_NOTICE, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue(Uuid::isValid($error->getId()));
        static::assertSame('checkout-gateway-error', $error->getMessageKey());
        static::assertSame(['reason' => 'test'], $error->getParameters());
        static::assertFalse($error->isPersistent());
    }
}
