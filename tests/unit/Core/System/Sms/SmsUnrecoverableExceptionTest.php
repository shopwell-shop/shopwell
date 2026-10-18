<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\SmsUnrecoverableException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsUnrecoverableException::class)]
class SmsUnrecoverableExceptionTest extends TestCase
{
    public function testARejectionOfTheProviderCarriesItsCode(): void
    {
        $exception = SmsUnrecoverableException::providerRejected('aliyun', 'isv.SMS_TEMPLATE_ILLEGAL', 'not approved');

        static::assertSame(SmsUnrecoverableException::PROVIDER_REJECTED, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('aliyun', $exception->getParameter('technicalName'));
        static::assertSame('isv.SMS_TEMPLATE_ILLEGAL', $exception->getParameter('providerCode'));
        static::assertStringContainsString('isv.SMS_TEMPLATE_ILLEGAL', $exception->getMessage());
    }

    public function testItIsAFailureOfTheComponentWithACodeOfItsOwn(): void
    {
        $exception = SmsUnrecoverableException::providerRejected('aliyun', 'isp.SYSTEM_ERROR', 'busy');

        static::assertSame(SmsUnrecoverableException::PROVIDER_REJECTED, $exception->getErrorCode());
        static::assertNotSame(SmsException::PROVIDER_UNAVAILABLE, $exception->getErrorCode());
    }

    public function testItNamesEveryCredentialTheGatewayIsMissing(): void
    {
        $exception = SmsUnrecoverableException::credentialsIncomplete('tencentcloud', ['secretId', 'sdkAppId']);

        static::assertSame(SmsUnrecoverableException::CREDENTIALS_INCOMPLETE, $exception->getErrorCode());
        static::assertSame('secretId, sdkAppId', $exception->getParameter('missing'));
        static::assertSame('tencentcloud', $exception->getParameter('technicalName'));
    }

    public function testItReportsAGatewayThatHasNoSignatureToSendWith(): void
    {
        $exception = SmsUnrecoverableException::signNameMissing('aliyun');

        static::assertSame(SmsUnrecoverableException::SIGN_NAME_MISSING, $exception->getErrorCode());
        static::assertSame('aliyun', $exception->getParameter('technicalName'));
    }
}
