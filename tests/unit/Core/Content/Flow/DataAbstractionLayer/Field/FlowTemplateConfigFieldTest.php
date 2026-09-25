<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\DataAbstractionLayer\Field\FlowTemplateConfigField;
use Shopwell\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowTemplateConfigField::class)]
class FlowTemplateConfigFieldTest extends TestCase
{
    private FlowTemplateConfigField $field;

    protected function setUp(): void
    {
        $this->field = new FlowTemplateConfigField('config', 'config');
    }

    public function testGetSerializerWillReturnFieldSerializerInterfaceInstance(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry
            ->method('getSerializer')
            ->willReturn(
                new FlowTemplateConfigFieldSerializer(
                    static::createStub(ValidatorInterface::class),
                    $registry
                )
            );

        $this->field->compile($registry);

        static::assertInstanceOf(FlowTemplateConfigFieldSerializer::class, $this->field->getSerializer());
    }
}
