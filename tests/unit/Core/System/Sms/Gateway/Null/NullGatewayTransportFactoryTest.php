<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Null;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Null\NullGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Null\NullSmsTransport;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NullGatewayTransportFactory::class)]
class NullGatewayTransportFactoryTest extends TestCase
{
    public function testItDescribesItself(): void
    {
        $factory = new NullGatewayTransportFactory();

        static::assertSame('null', $factory->getTechnicalName());
        static::assertSame('sms.gateway.null', $factory->getLabelSnippet());
        static::assertSame(['GLOBAL'], $factory->getSupportedRegions());
        static::assertFalse($factory->supportsTemplateMode());
        static::assertSame([], $factory->getCredentialDefinitions());
    }

    public function testItBuildsTheNullTransport(): void
    {
        $transport = (new NullGatewayTransportFactory())->create([], []);

        // the constructor takes no credentials, the built transport is the one that swallows a message
        static::assertInstanceOf(NullSmsTransport::class, $transport);
        static::assertSame('null', (string) $transport);
    }
}
