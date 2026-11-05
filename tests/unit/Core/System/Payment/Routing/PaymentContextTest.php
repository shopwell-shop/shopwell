<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Routing\PaymentContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentContext::class)]
class PaymentContextTest extends TestCase
{
    public function testDeviceIsDerivedFromTheExplicitEntryPoint(): void
    {
        $context = new PaymentContext('CNY', new PaymentScene('mini_program', 'mini', 'wechat'));
        static::assertSame('mini', $context->device);
        static::assertSame([], $context->supportedSdkProducts);
        static::assertSame([], $context->runtimeParams);
    }
}
