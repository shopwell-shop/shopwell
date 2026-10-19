<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Transport;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Gateway\Null\NullGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Null\NullSmsTransport;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Shopwell\Core\System\Sms\Transport\SmsTransportLoader;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTransportLoader::class)]
class SmsTransportLoaderTest extends TestCase
{
    public function testItBuildsTheLazyCollectionOfTheTransports(): void
    {
        // the dsns of the framework are deliberately not used
        $transports = $this->createLoader()->fromConfig(['null' => 'null://null']);

        static::assertSame('sms-transports', (string) $transports);
        static::assertTrue($transports->supports(new SmsMessage('+8613800000000', 'hello')));
    }

    public function testItFallsBackToTheNullGatewayForTheNullName(): void
    {
        static::assertInstanceOf(
            NullSmsTransport::class,
            $this->createLoader()->fromGatewayId(SmsGatewayResolverInterface::GATEWAY_NULL)
        );
    }

    public function testItBuildsTheTransportOfAConfiguredGateway(): void
    {
        $gateway = $this->createGateway('aliyun');
        $expected = static::createStub(TransportInterface::class);

        $factory = static::createMock(SmsGatewayTransportFactoryInterface::class);
        $factory->method('getTechnicalName')->willReturn('aliyun');
        $factory->method('getSupportedRegions')->willReturn(['CN']);
        $factory->method('supportsTemplateMode')->willReturn(true);
        $factory->expects($this->once())
            ->method('create')
            ->with(['accessKeyId' => 'key'], ['sandbox' => true])
            ->willReturn($expected);

        $loader = $this->createLoader([$gateway], new SmsGatewayRegistry([$factory]));

        static::assertSame($expected, $loader->fromGatewayId($gateway->id));
    }

    public function testItFallsBackToTheNullGatewayForADisabledGateway(): void
    {
        $gateway = $this->createGateway('aliyun', enabled: false);

        static::assertInstanceOf(
            NullSmsTransport::class,
            $this->createLoader([$gateway], new SmsGatewayRegistry([new NullGatewayTransportFactory()]))->fromGatewayId($gateway->id)
        );
    }

    public function testItFallsBackToTheNullGatewayForAnUnknownId(): void
    {
        static::assertInstanceOf(
            NullSmsTransport::class,
            $this->createLoader()->fromGatewayId(Uuid::randomHex())
        );
    }

    public function testItFallsBackToTheNullGatewayAndWarnsForAProviderThatIsGone(): void
    {
        $gateway = $this->createGateway('removed-provider');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'The sms gateway of the message is not registered, the message is not sent.',
                ['technicalName' => 'removed-provider', 'gatewayId' => $gateway->id]
            );

        $loader = new SmsTransportLoader(
            new SmsGatewayRegistry([]),
            $this->createGatewayRepository([$gateway]),
            $logger,
        );

        static::assertInstanceOf(NullSmsTransport::class, $loader->fromGatewayId($gateway->id));
    }

    public function testItFallsBackToTheNullGatewayWhenTheDatabaseIsNotReachable(): void
    {
        // this is what happens while the installation is running
        $repository = StaticEntityRepository::of(SmsGatewayCollection::class, [
            static function (): never {
                throw new DriverException(
                    new class extends \Exception implements DriverExceptionInterface {
                        public function getSQLState(): string
                        {
                            return 'HY000';
                        }
                    },
                    null
                );
            },
        ]);

        $loader = new SmsTransportLoader(new SmsGatewayRegistry([]), $repository, static::createStub(LoggerInterface::class));

        static::assertInstanceOf(NullSmsTransport::class, $loader->fromGatewayId(Uuid::randomHex()));
    }

    /**
     * @param list<SmsGatewayEntity> $gateways
     *
     * @return StaticEntityRepository<SmsGatewayCollection>
     */
    private function createGatewayRepository(array $gateways): StaticEntityRepository
    {
        return StaticEntityRepository::of(SmsGatewayCollection::class, [new SmsGatewayCollection($gateways)]);
    }

    /**
     * @param list<SmsGatewayEntity> $gateways
     */
    private function createLoader(array $gateways = [], ?SmsGatewayRegistry $registry = null): SmsTransportLoader
    {
        return new SmsTransportLoader(
            $registry ?? new SmsGatewayRegistry([new NullGatewayTransportFactory()]),
            $this->createGatewayRepository($gateways),
            static::createStub(LoggerInterface::class),
        );
    }

    private function createGateway(string $technicalName, bool $enabled = true): SmsGatewayEntity
    {
        $gateway = new SmsGatewayEntity();
        $gateway->id = Uuid::randomHex();
        $gateway->setUniqueIdentifier($gateway->id);
        $gateway->technicalName = $technicalName;
        $gateway->enabled = $enabled;
        $gateway->credentials = ['accessKeyId' => 'key'];
        $gateway->options = ['sandbox' => true];

        return $gateway;
    }
}
