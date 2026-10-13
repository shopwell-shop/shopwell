<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Gateway\Aliyun;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Gateway\Aliyun\AliyunSmsOptions;
use Shopwell\Core\Framework\Sms\Struct\TemplateSmsOptionsInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AliyunSmsOptions::class)]
class AliyunSmsOptionsTest extends TestCase
{
    public function testItCarriesTheTemplateTheSignatureAndTheParameters(): void
    {
        $options = new AliyunSmsOptions('SMS_123456789', 'Shopwell', ['code' => '1234'], 'out-1');

        static::assertInstanceOf(TemplateSmsOptionsInterface::class, $options);
        static::assertSame('SMS_123456789', $options->getTemplateCode());
        static::assertSame('Shopwell', $options->getSignName());
        static::assertSame(['code' => '1234'], $options->getParams());
        static::assertSame('out-1', $options->getOutId());
        static::assertTrue($options->isTemplateBased());
        static::assertNull($options->getRecipientId());
    }

    public function testAMessageWithoutATemplateIsNotTemplateBased(): void
    {
        $options = new AliyunSmsOptions();

        static::assertFalse($options->isTemplateBased());
        static::assertNull($options->getTemplateCode());
        static::assertNull($options->getSignName());
        static::assertNull($options->getOutId());
        static::assertSame([], $options->getParams());
    }

    public function testAnEmptyTemplateCodeIsNotTemplateBased(): void
    {
        static::assertFalse((new AliyunSmsOptions(''))->isTemplateBased());
    }

    public function testItLeavesTheEmptyPartsOutWhenItIsSerialised(): void
    {
        static::assertSame(['templateCode' => 'SMS_1'], (new AliyunSmsOptions('SMS_1'))->toArray());
        static::assertSame([], (new AliyunSmsOptions())->toArray());
        static::assertSame([
            'templateCode' => 'SMS_1',
            'signName' => 'Shopwell',
            'outId' => 'out-1',
            'params' => ['code' => '1234'],
        ], (new AliyunSmsOptions('SMS_1', 'Shopwell', ['code' => '1234'], 'out-1'))->toArray());
    }
}
