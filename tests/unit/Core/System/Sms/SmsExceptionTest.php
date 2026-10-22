<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Exception\SmsUnrecoverableException;
use Shopwell\Core\System\Sms\SmsException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsException::class)]
class SmsExceptionTest extends TestCase
{
    /**
     * @return iterable<string, array{callable(): SmsException, string, int}>
     */
    public static function factoryProvider(): iterable
    {
        yield 'unknown gateway' => [
            static fn (): SmsException => SmsException::unknownGateway('twilio', ['aliyun']),
            SmsException::UNKNOWN_GATEWAY,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'invalid phone number' => [
            static fn (): SmsException => SmsException::invalidPhoneNumber('nope'),
            SmsException::INVALID_PHONE_NUMBER,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'phone number without country code' => [
            static fn (): SmsException => SmsException::phoneNumberNotInternational('13800000000'),
            SmsException::PHONE_NUMBER_NOT_INTERNATIONAL,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'template not found' => [
            static fn (): SmsException => SmsException::templateNotFound('customer.otp.login'),
            SmsException::TEMPLATE_NOT_FOUND,
            Response::HTTP_NOT_FOUND,
        ];

        yield 'template disabled' => [
            static fn (): SmsException => SmsException::templateDisabled('customer.otp.login'),
            SmsException::TEMPLATE_DISABLED,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'template mode required' => [
            static fn (): SmsException => SmsException::templateModeRequired('aliyun'),
            SmsException::TEMPLATE_MODE_REQUIRED,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'template not configured' => [
            static fn (): SmsException => SmsException::templateNotConfigured('customer.otp.login', 'aliyun'),
            SmsException::TEMPLATE_NOT_CONFIGURED,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'template not approved' => [
            static fn (): SmsException => SmsException::templateNotApproved('customer.otp.login', 'aliyun', 'pending'),
            SmsException::TEMPLATE_NOT_APPROVED,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'template content missing' => [
            static fn (): SmsException => SmsException::templateContentMissing('customer.otp.login'),
            SmsException::TEMPLATE_CONTENT_MISSING,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'invalid template params' => [
            static fn (): SmsException => SmsException::invalidTemplateParams('customer.otp.login', ['code is missing']),
            SmsException::INVALID_TEMPLATE_PARAMS,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'message too long' => [
            static fn (): SmsException => SmsException::messageTooLong('customer.otp.login', 200, 160),
            SmsException::MESSAGE_TOO_LONG,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'provider unavailable' => [
            static fn (): SmsException => SmsException::providerUnavailable('aliyun', 'isp.SYSTEM_ERROR', 'busy'),
            SmsException::PROVIDER_UNAVAILABLE,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'provider rejected' => [
            static fn (): SmsException => SmsException::providerRejected('aliyun', 'isv.SMS_TEMPLATE_ILLEGAL', 'not approved'),
            SmsException::PROVIDER_REJECTED,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'credentials incomplete' => [
            static fn (): SmsException => SmsException::credentialsIncomplete('tencentcloud', ['secretId', 'sdkAppId']),
            SmsException::CREDENTIALS_INCOMPLETE,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'sign name missing' => [
            static fn (): SmsException => SmsException::signNameMissing('aliyun'),
            SmsException::SIGN_NAME_MISSING,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'sign name not approved' => [
            static fn (): SmsException => SmsException::signNameNotApproved('aliyun', 'Shopwell', 'isv.SMS_SIGN_ILLEGAL', 'illegal'),
            SmsException::SIGN_NAME_NOT_APPROVED,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ];

        yield 'template submission unsupported' => [
            static fn (): SmsException => SmsException::templateSubmissionUnsupported('log'),
            SmsException::TEMPLATE_SUBMISSION_UNSUPPORTED,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'template status read unsupported' => [
            static fn (): SmsException => SmsException::templateStatusReadUnsupported('log'),
            SmsException::TEMPLATE_STATUS_READ_UNSUPPORTED,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'template submission invalid' => [
            static fn (): SmsException => SmsException::templateSubmissionInvalid('customer.otp.login', 'aliyun', ['content is required']),
            SmsException::TEMPLATE_SUBMISSION_INVALID,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'template audit account required' => [
            static fn (): SmsException => SmsException::templateAuditAccountRequired('customer.otp.login', 'aliyun'),
            SmsException::TEMPLATE_AUDIT_ACCOUNT_REQUIRED,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'template audit response invalid' => [
            static fn (): SmsException => SmsException::templateAuditResponseInvalid('aliyun', 'no template code'),
            SmsException::TEMPLATE_AUDIT_RESPONSE_INVALID,
            Response::HTTP_BAD_GATEWAY,
        ];

        yield 'template audit confirmation invalid' => [
            static fn (): SmsException => SmsException::templateAuditConfirmationInvalid('customer.otp.login', 'aliyun', ['note is required']),
            SmsException::TEMPLATE_AUDIT_CONFIRMATION_INVALID,
            Response::HTTP_BAD_REQUEST,
        ];
    }

    /**
     * @param callable(): SmsException $factory
     */
    #[DataProvider('factoryProvider')]
    public function testEveryFactoryCarriesItsCodeAndStatus(callable $factory, string $expectedCode, int $expectedStatus): void
    {
        $exception = $factory();

        static::assertSame($expectedCode, $exception->getErrorCode());
        static::assertSame($expectedStatus, $exception->getStatusCode());
    }

    public function testTheFailuresThatMayPassLaterAreThePlainException(): void
    {
        static::assertNotInstanceOf(SmsUnrecoverableException::class, SmsException::providerUnavailable('aliyun', 'isp.SYSTEM_ERROR', 'busy'));
        static::assertNotInstanceOf(SmsUnrecoverableException::class, SmsException::invalidPhoneNumber('nope'));
    }

    public function testTheSignatureFailureIsNotReportedAsATemplateFailure(): void
    {
        $signature = SmsException::signNameNotApproved('aliyun', 'Shopwell', 'isv.SMS_SIGN_ILLEGAL', 'illegal');
        $template = SmsException::providerRejected('aliyun', 'isv.SMS_TEMPLATE_ILLEGAL', 'not approved');

        static::assertNotSame($signature->getErrorCode(), $template->getErrorCode());
        static::assertStringContainsString('Shopwell', $signature->getMessage());
        static::assertSame('Shopwell', $signature->getParameter('signName'));
    }

    public function testItKeepsTheNumbersAPhoneNumberFailureNeedsToBeDiagnosed(): void
    {
        $exception = SmsException::phoneNumberNotInternational('13800000000');

        static::assertSame('13800000000', $exception->getParameter('phone'));
        static::assertStringContainsString('13800000000', $exception->getMessage());
    }
}
