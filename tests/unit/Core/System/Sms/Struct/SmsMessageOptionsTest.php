<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Struct\SmsMessageOptions;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsMessageOptions::class)]
class SmsMessageOptionsTest extends TestCase
{
    public function testAFreeTextMessageCarriesTheSceneAndTheTemplateOnly(): void
    {
        $options = new SmsMessageOptions(
            scene: SmsTemplateEntity::SCENE_OTP,
            templateRef: 'customer.otp.login',
        );

        static::assertSame(SmsTemplateEntity::SCENE_OTP, $options->getScene());
        static::assertSame('customer.otp.login', $options->getTemplateRef());
        static::assertNull($options->getTemplateCode());
        static::assertNull($options->getSignName());
        static::assertSame([], $options->getParams());
        static::assertFalse($options->isTemplateBased());
        static::assertNull($options->getRecipientId());
        static::assertSame(['scene' => 'otp', 'templateRef' => 'customer.otp.login'], $options->toArray());
    }

    public function testATemplateOfAProviderCountsAsTemplateBased(): void
    {
        $options = new SmsMessageOptions(
            scene: SmsTemplateEntity::SCENE_TRANSACTION,
            templateRef: 'order.shipped',
            templateCode: 'SMS_123456789',
            signName: 'Shopwell',
            params: ['orderNumber' => '10001'],
        );

        static::assertTrue($options->isTemplateBased());
        static::assertSame('SMS_123456789', $options->getTemplateCode());
        static::assertSame('Shopwell', $options->getSignName());
        static::assertSame(['orderNumber' => '10001'], $options->getParams());
        static::assertSame([
            'scene' => 'transaction',
            'templateRef' => 'order.shipped',
            'templateCode' => 'SMS_123456789',
            'signName' => 'Shopwell',
            'params' => ['orderNumber' => '10001'],
        ], $options->toArray());
    }

    public function testAnEmptyTemplateCodeIsNotTemplateBased(): void
    {
        $options = new SmsMessageOptions(templateCode: '');

        static::assertFalse($options->isTemplateBased());
        static::assertSame(SmsTemplateEntity::SCENE_TRANSACTION, $options->getScene());
    }
}
