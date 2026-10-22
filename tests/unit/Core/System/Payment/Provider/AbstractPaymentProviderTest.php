<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentDecorator;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentProvider;

/**
 * Guards the defaults of the contract.
 *
 * Every default is a decision: an interface that says nothing must not claim a capability, must not
 * pay out and must not be charged repeatedly. The one default that throws is the base implementation's
 * `getDecorated()` - that is what makes the decoration chain terminate.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractPaymentProvider::class)]
class AbstractPaymentProviderTest extends TestCase
{
    public function testAQuietDeclarationClaimsNothing(): void
    {
        $provider = new TestPaymentProvider();

        static::assertNull($provider->getDescription());
        static::assertSame([], $provider->getCredentials());
        static::assertSame([], $provider->getChannels());
        static::assertSame([], $provider->getChannelCaptureActions());
        static::assertSame([], $provider->getApiArguments());
        static::assertNull($provider->getPayParamsDecoder());
        static::assertSame([], $provider->getDeclaredCapabilities());
        static::assertNull($provider->getTransferRules(), 'An interface that says nothing cannot pay out.');
        static::assertNull($provider->getCycleRules(), 'An interface that says nothing cannot be charged repeatedly.');
        static::assertNull($provider->getTransferNotificationVerifier());
        static::assertNull($provider->getSourceName(), 'A core interface has no plugin source.');
    }

    public function testTheBaseImplementationHasNoDecoratedInstance(): void
    {
        $this->expectException(DecorationPatternException::class);

        (new TestPaymentProvider())->getDecorated();
    }

    public function testADecoratorReturnsTheDeclarationItWraps(): void
    {
        $base = new TestPaymentProvider(name: 'testpay');
        $decorator = new TestPaymentDecorator($base);

        static::assertSame($base, $decorator->getDecorated());
        static::assertSame('testpay', $decorator->getName(), 'A decorator keeps the technical name of the declaration it wraps.');
    }
}
