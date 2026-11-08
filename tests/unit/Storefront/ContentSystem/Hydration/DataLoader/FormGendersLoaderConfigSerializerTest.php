<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\ContentSystem\Hydration\DataLoader\FormGendersLoaderConfig;
use Shopwell\Storefront\ContentSystem\Hydration\DataLoader\FormGendersLoaderConfigSerializer;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FormGendersLoaderConfigSerializer::class)]
class FormGendersLoaderConfigSerializerTest extends TestCase
{
    public function testRoundTripsAnEmptyConfig(): void
    {
        $serializer = new FormGendersLoaderConfigSerializer();

        static::assertInstanceOf(FormGendersLoaderConfig::class, $serializer->decode([]));
        static::assertSame([], $serializer->encode($serializer->decode([])));
        static::assertSame('form_genders', $serializer::getSource());
    }

    public function testRejectsUnsupportedConfigKeys(): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidFieldValueType('config', 'empty map', 'non-empty map'));

        (new FormGendersLoaderConfigSerializer())->decode(['group' => 'anything']);
    }
}
