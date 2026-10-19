<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatPlatformCertificates;

/**
 * The platform rotates its certificates, so an installation may hold one certificate with its serial
 * or a map of serial to certificate. Both reach the verifier and the api in the same shape.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatPlatformCertificates::class)]
class WechatPlatformCertificatesTest extends TestCase
{
    public function testASingleCertificateIsIndexedByItsSerial(): void
    {
        $certificates = WechatPlatformCertificates::of([
            WechatApi::PLATFORM_CERT => 'certificate-a',
            WechatApi::PLATFORM_SERIAL => 'SERIAL-A',
        ]);

        static::assertSame(['SERIAL-A' => 'certificate-a'], $certificates);
    }

    public function testASingleCertificateWithoutASerialIsIndexedAsPlatform(): void
    {
        static::assertSame(
            ['platform' => 'certificate-a'],
            WechatPlatformCertificates::of([WechatApi::PLATFORM_CERT => 'certificate-a'])
        );
    }

    public function testAJsonMapIsDecoded(): void
    {
        $certificates = WechatPlatformCertificates::of([
            WechatApi::PLATFORM_CERTS => '{"SERIAL-A":"certificate-a","SERIAL-B":"certificate-b"}',
        ]);

        static::assertSame(['SERIAL-A' => 'certificate-a', 'SERIAL-B' => 'certificate-b'], $certificates);
    }

    public function testBothWaysOfConfiguringAreMerged(): void
    {
        $certificates = WechatPlatformCertificates::of([
            WechatApi::PLATFORM_CERTS => ['SERIAL-A' => 'certificate-a'],
            WechatApi::PLATFORM_CERT => 'certificate-b',
            WechatApi::PLATFORM_SERIAL => 'SERIAL-B',
        ]);

        static::assertSame(['SERIAL-A' => 'certificate-a', 'SERIAL-B' => 'certificate-b'], $certificates);
    }

    /**
     * An empty value is no certificate, and a row that holds only empty values is as incomplete as a
     * row without any - the verifier must not be handed a map it can never match.
     */
    public function testEmptyEntriesAreIgnored(): void
    {
        $this->expectExceptionObject(PaymentException::missingCredentials(WechatApi::PROVIDER, WechatApi::PLATFORM_CERTS));

        WechatPlatformCertificates::of([
            WechatApi::PLATFORM_CERTS => ['SERIAL-A' => '', 'SERIAL-B' => ['not' => 'scalar']],
            WechatApi::PLATFORM_CERT => '',
        ]);
    }

    public function testWithoutAnyCertificateTheCredentialsAreIncomplete(): void
    {
        $this->expectExceptionObject(PaymentException::missingCredentials(WechatApi::PROVIDER, WechatApi::PLATFORM_CERTS));

        WechatPlatformCertificates::of([]);
    }

    public function testTheNamedSerialWins(): void
    {
        $certificates = [WechatApi::PLATFORM_CERTS => ['SERIAL-A' => 'certificate-a', 'SERIAL-B' => 'certificate-b']];

        static::assertSame('certificate-b', WechatPlatformCertificates::certificateOf($certificates, 'SERIAL-B'));
    }

    /**
     * An unknown serial is not necessarily an attack: a channel may rotate before the installation was
     * updated. With a single certificate there is nothing to guess.
     */
    public function testASingleCertificateServesAnUnknownSerial(): void
    {
        $certificates = [WechatApi::PLATFORM_CERT => 'certificate-a', WechatApi::PLATFORM_SERIAL => 'SERIAL-A'];

        static::assertSame('certificate-a', WechatPlatformCertificates::certificateOf($certificates, 'SERIAL-NEW'));
    }

    /**
     * With several certificates and none named, guessing would verify against a certificate the channel
     * did not sign with.
     */
    public function testAnUnknownSerialWithSeveralCertificatesIsNoCertificate(): void
    {
        $certificates = [WechatApi::PLATFORM_CERTS => ['SERIAL-A' => 'certificate-a', 'SERIAL-B' => 'certificate-b']];

        static::assertNull(WechatPlatformCertificates::certificateOf($certificates, 'SERIAL-NEW'));
    }

    public function testTheOnlyCertificateServesAMissingSerial(): void
    {
        $certificates = [WechatApi::PLATFORM_CERT => 'certificate-a', WechatApi::PLATFORM_SERIAL => 'SERIAL-A'];

        static::assertSame('certificate-a', WechatPlatformCertificates::certificateOf($certificates, null));
    }
}
