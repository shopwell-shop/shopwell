<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateSubmission::class)]
class TemplateSubmissionTest extends TestCase
{
    public function testItCarriesWhatTheProviderNeedsToReportTheTemplate(): void
    {
        $submission = new TemplateSubmission(
            templateRef: 'customer.otp.login',
            scene: SmsTemplateEntity::SCENE_OTP,
            content: 'Your code is {{ code }}.',
            signName: 'Shopwell',
            paramNames: ['code'],
            values: ['templateContent' => 'Your code is {{ code }}.'],
            existingTemplateCode: 'SMS_123456789',
        );

        static::assertSame('customer.otp.login', $submission->templateRef);
        static::assertSame(SmsTemplateEntity::SCENE_OTP, $submission->scene);
        static::assertSame('Your code is {{ code }}.', $submission->content);
        static::assertSame('Shopwell', $submission->signName);
        static::assertSame(['code'], $submission->paramNames);
        static::assertSame(['templateContent' => 'Your code is {{ code }}.'], $submission->values);
        static::assertSame('SMS_123456789', $submission->existingTemplateCode);
    }

    public function testAFirstSubmissionCarriesNoTemplateCodeAndNoSignature(): void
    {
        $submission = new TemplateSubmission(
            templateRef: 'customer.otp.login',
            scene: SmsTemplateEntity::SCENE_OTP,
            content: 'Your code is {{ code }}.',
            signName: null,
            paramNames: [],
            values: [],
            existingTemplateCode: null,
        );

        static::assertNull($submission->signName);
        static::assertNull($submission->existingTemplateCode);
        static::assertSame([], $submission->values);
    }
}
