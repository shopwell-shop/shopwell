<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Payment\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Payment\Response\AbstractResponse;
use Shopwell\Core\Framework\App\Payment\Response\ValidateResponse;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ValidateResponse::class)]
#[CoversClass(AbstractResponse::class)]
class ValidateResponseTest extends TestCase
{
    public function testEmpty(): void
    {
        $response = new ValidateResponse();
        static::assertSame([], $response->getPreOrderPayment());
    }

    public function testNonEmpty(): void
    {
        $response = new ValidateResponse();
        $response->assign([
            'preOrderPayment' => [
                'foo' => 'bar',
            ],
        ]);
        static::assertSame(['foo' => 'bar'], $response->getPreOrderPayment());
    }
}
