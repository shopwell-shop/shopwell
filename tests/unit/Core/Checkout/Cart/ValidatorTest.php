<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartValidatorInterface;
use Shopwell\Core\Checkout\Cart\Error\Error;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Cart\Validator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Validator::class)]
class ValidatorTest extends TestCase
{
    public function testValidate(): void
    {
        $mockValidator = $this->createMock(CartValidatorInterface::class);
        $mockValidator2 = new class($this->createStub(Error::class)) implements CartValidatorInterface {
            public function __construct(private readonly Error $error)
            {
            }

            public function validate(
                Cart $cart,
                ErrorCollection $errors,
                SalesChannelContext $context
            ): void {
                $errors->add($this->error);
            }
        };
        $validator = new Validator([$mockValidator, $mockValidator2]);
        $context = static::createStub(SalesChannelContext::class);
        $cart = new Cart('test');

        $mockValidator->expects($this->once())->method('validate')->with($cart, static::anything(), $context);

        $errors = $validator->validate($cart, $context);
        static::assertCount(1, $errors);
    }
}
