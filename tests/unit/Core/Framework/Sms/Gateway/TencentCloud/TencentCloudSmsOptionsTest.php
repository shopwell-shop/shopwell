<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Gateway\TencentCloud;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Gateway\TencentCloud\TencentCloudSmsOptions;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TencentCloudSmsOptions::class)]
class TencentCloudSmsOptionsTest extends TestCase
{
    public function testItCarriesTheTemplateTheSignatureAndTheParameters(): void
    {
        $options = new TencentCloudSmsOptions('1234567', 'Shopwell', ['1' => 'code'], 'session');

        static::assertSame('1234567', $options->getTemplateCode());
        static::assertSame('Shopwell', $options->getSignName());
        static::assertSame(['1' => 'code'], $options->getParams());
        static::assertSame('session', $options->getSessionContext());
        static::assertTrue($options->isTemplateBased());
        static::assertNull($options->getRecipientId());
    }

    public function testAMessageWithoutATemplateIsNotTemplateBased(): void
    {
        $options = new TencentCloudSmsOptions();

        static::assertFalse($options->isTemplateBased());
        static::assertNull($options->getTemplateCode());
        static::assertNull($options->getSignName());
        static::assertNull($options->getSessionContext());
        static::assertSame([], $options->getParams());
    }

    public function testAnEmptyTemplateCodeIsNotTemplateBased(): void
    {
        static::assertFalse((new TencentCloudSmsOptions(''))->isTemplateBased());
    }

    public function testItLeavesTheEmptyPartsOutWhenItIsSerialised(): void
    {
        static::assertSame(['templateCode' => '1234567'], (new TencentCloudSmsOptions('1234567'))->toArray());
        static::assertSame([], (new TencentCloudSmsOptions())->toArray());
        static::assertSame([
            'templateCode' => '1234567',
            'signName' => 'Shopwell',
            'sessionContext' => 'session',
            'params' => ['1' => 'code'],
        ], (new TencentCloudSmsOptions('1234567', 'Shopwell', ['1' => 'code'], 'session'))->toArray());
    }
}
