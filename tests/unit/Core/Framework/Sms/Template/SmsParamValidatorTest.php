<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateEntity;
use Shopwell\Core\Framework\Sms\SmsException;
use Shopwell\Core\Framework\Sms\Template\SmsParamValidator;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsParamValidator::class)]
class SmsParamValidatorTest extends TestCase
{
    public function testItAcceptsTheParametersTheTemplateDeclares(): void
    {
        $this->expectNotToPerformAssertions();

        $template = $this->createTemplate([
            ['name' => 'code', 'required' => true, 'pattern' => '^\d{6}$'],
        ]);

        (new SmsParamValidator())->validate($template, ['code' => '123456']);
    }

    public function testItAcceptsAnIntegerWhereThePatternExpectsDigits(): void
    {
        $this->expectNotToPerformAssertions();

        $template = $this->createTemplate([
            ['name' => 'minutes', 'required' => true, 'pattern' => '^\d+$'],
        ]);

        (new SmsParamValidator())->validate($template, ['minutes' => 15]);
    }

    public function testItThrowsForAMissingRequiredParameter(): void
    {
        $template = $this->createTemplate([
            ['name' => 'code', 'required' => true],
        ]);

        $this->expectExceptionObject(SmsException::invalidTemplateParams('customer.otp.login', ['"code" is required']));

        (new SmsParamValidator())->validate($template, []);
    }

    public function testItIgnoresAMissingOptionalParameter(): void
    {
        $this->expectNotToPerformAssertions();

        $template = $this->createTemplate([
            ['name' => 'code', 'required' => true],
            ['name' => 'shopName', 'required' => false],
        ]);

        (new SmsParamValidator())->validate($template, ['code' => '123456']);
    }

    public function testItThrowsForAParameterThatDoesNotMatchThePattern(): void
    {
        $template = $this->createTemplate([
            ['name' => 'code', 'required' => true, 'pattern' => '^\d{6}$'],
        ]);

        $this->expectExceptionObject(SmsException::invalidTemplateParams(
            'customer.otp.login',
            ['"code" does not match ^\d{6}$']
        ));

        (new SmsParamValidator())->validate($template, ['code' => 'abc']);
    }

    public function testItThrowsForAnArrayWhereThePatternExpectsAValue(): void
    {
        $template = $this->createTemplate([
            ['name' => 'code', 'required' => true, 'pattern' => '^\d+$'],
        ]);

        $this->expectExceptionObject(SmsException::invalidTemplateParams(
            'customer.otp.login',
            ['"code" does not match ^\d+$']
        ));

        (new SmsParamValidator())->validate($template, ['code' => ['123456']]);
    }

    public function testItReportsEveryViolationAtOnce(): void
    {
        $template = $this->createTemplate([
            ['name' => 'code', 'required' => true],
            ['name' => 'minutes', 'required' => true, 'pattern' => '^\d+$'],
        ]);

        $this->expectExceptionObject(SmsException::invalidTemplateParams(
            'customer.otp.login',
            ['"code" is required', '"minutes" does not match ^\d+$']
        ));

        (new SmsParamValidator())->validate($template, ['minutes' => 'soon']);
    }

    public function testATemplateWithoutAContractAcceptsEverything(): void
    {
        $this->expectNotToPerformAssertions();

        (new SmsParamValidator())->validate($this->createTemplate(null), ['anything' => 'goes']);
    }

    public function testItSkipsAnEntryOfTheContractWithoutAName(): void
    {
        $this->expectNotToPerformAssertions();

        $template = $this->createTemplate([
            ['required' => true],
        ]);

        (new SmsParamValidator())->validate($template, []);
    }

    /**
     * @param list<array<string, mixed>>|null $paramsSchema
     */
    private function createTemplate(?array $paramsSchema): SmsTemplateEntity
    {
        $template = new SmsTemplateEntity();
        $template->id = Uuid::randomHex();
        $template->setUniqueIdentifier($template->id);
        $template->technicalName = 'customer.otp.login';
        $template->scene = SmsTemplateEntity::SCENE_OTP;
        $template->paramsSchema = $paramsSchema;
        $template->enabled = true;

        return $template;
    }
}
