<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopwell\Core\Content\ImportExport\ImportExportException;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(SerializerRegistry::class)]
class SerializerRegistryTest extends TestCase
{
    public function testGetEntityThrowsExceptionBecauseNoSerializerFound(): void
    {
        $registry = new SerializerRegistry([], []);

        $this->expectExceptionObject(ImportExportException::serializerNotFound('test'));
        $registry->getEntity('test');
    }

    public function testGetFieldSerializerThrowsExceptionBecauseNoSerializerFound(): void
    {
        $registry = new SerializerRegistry([], []);

        $field = new BoolField('test', 'test');
        $this->expectExceptionObject(ImportExportException::serializerNotFound($field::class));
        $registry->getFieldSerializer($field);
    }
}
