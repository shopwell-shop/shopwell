<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Gateway\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Gateway\Log\LogGatewayTransportFactory;
use Shopwell\Core\Framework\Sms\Gateway\Log\LogSmsTransport;
use Shopwell\Core\Framework\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LogGatewayTransportFactory::class)]
class LogGatewayTransportFactoryTest extends TestCase
{
    public function testItDescribesItself(): void
    {
        $factory = $this->createFactory(static::createStub(LoggerInterface::class));

        static::assertSame('log', $factory->getTechnicalName());
        static::assertSame('sms.gateway.log', $factory->getLabelSnippet());
        static::assertSame(['GLOBAL'], $factory->getSupportedRegions());
        static::assertFalse($factory->supportsTemplateMode());
        static::assertSame([], $factory->getCredentialDefinitions());
    }

    public function testItBuildsTheLogTransportAndLogsTheMaskedNumber(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('sms.message.logged', ['phone' => '+86138****0000', 'subject' => 'hello']);

        $transport = $this->createFactory($logger)->create([], []);

        static::assertInstanceOf(LogSmsTransport::class, $transport);
        static::assertSame('log', $transport->send(new SmsMessage('+8613800000000', 'hello'))->getTransport());
    }

    private function createFactory(LoggerInterface $logger): LogGatewayTransportFactory
    {
        return new LogGatewayTransportFactory(
            $logger,
            new PhoneNumberNormalizer(new StaticSystemConfigService()),
        );
    }
}
