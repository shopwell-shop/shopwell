<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\CustomEntity\Xml\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Shopwell\Tests\Unit\Core\System\CustomEntity\Xml\Config\Fixture\TestElement;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigXmlElement::class)]
class ConfigXmlElementTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $extendedConfigXmlElement = TestElement::fromArray([]);

        $serializeResult = $extendedConfigXmlElement->jsonSerialize();
        static::assertSame(['testData' => 'TEST_DATA'], $serializeResult);

        static::assertSame([], $extendedConfigXmlElement->extensions);
        static::assertSame('TEST_DATA', $extendedConfigXmlElement->testData);
    }
}
