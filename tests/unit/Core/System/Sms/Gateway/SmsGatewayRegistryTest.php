<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Log\LogGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Null\NullGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsGatewayRegistry::class)]
class SmsGatewayRegistryTest extends TestCase
{
    public function testIndexesTheFactoriesByTechnicalName(): void
    {
        $null = new NullGatewayTransportFactory();
        $log = new LogGatewayTransportFactory(
            new NullLogger(),
            new PhoneNumberNormalizer(new StaticSystemConfigService())
        );

        $registry = new SmsGatewayRegistry([$null, $log]);

        static::assertTrue($registry->has('null'));
        static::assertTrue($registry->has('log'));
        static::assertFalse($registry->has('aliyun'));
        static::assertSame($null, $registry->get('null'));
        static::assertSame($log, $registry->get('log'));
        static::assertSame(['null' => $null, 'log' => $log], $registry->all());
    }

    public function testWorksWithoutAnyRegisteredGateway(): void
    {
        $registry = new SmsGatewayRegistry([]);

        static::assertFalse($registry->has('null'));
        static::assertSame([], $registry->all());
    }

    public function testADuplicateTechnicalNameIsResolvedToTheLastFactory(): void
    {
        $first = new NullGatewayTransportFactory();
        $second = new NullGatewayTransportFactory();

        $registry = new SmsGatewayRegistry([$first, $second]);

        static::assertSame($second, $registry->get('null'));
    }

    public function testThrowsForAGatewayThatWasNotRegistered(): void
    {
        $registry = new SmsGatewayRegistry([new NullGatewayTransportFactory()]);

        $this->expectExceptionObject(SmsException::unknownGateway('aliyun', ['null']));

        $registry->get('aliyun');
    }
}
