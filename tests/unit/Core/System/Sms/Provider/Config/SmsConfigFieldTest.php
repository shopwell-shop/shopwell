<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Provider\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\Config\SmsConfigField;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsConfigField::class)]
class SmsConfigFieldTest extends TestCase
{
    public function testKeepsFalseDefaultsAndSelectOptionsInTheFormDescription(): void
    {
        $field = new SmsConfigField('sandbox', 'plugin.sms.sandbox', type: 'bool', default: false);

        static::assertSame([
            'name' => 'sandbox', 'type' => 'bool', 'required' => false,
            'labelSnippet' => 'plugin.sms.sandbox', 'default' => false,
        ], $field->toArray());
    }
}
