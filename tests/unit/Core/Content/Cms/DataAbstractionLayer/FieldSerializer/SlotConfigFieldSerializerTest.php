<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cms\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\DataAbstractionLayer\Field\SlotConfigField;
use Shopwell\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SlotConfigFieldSerializer::class)]
class SlotConfigFieldSerializerTest extends TestCase
{
    public function testEncodeUsesSlotConfigFieldSerializerConstraints(): void
    {
        $id = Uuid::randomHex();
        $expected = new All(
            constraints: new Collection(
                fields: [
                    'source' => [
                        new Choice(
                            choices: [
                                FieldConfig::SOURCE_STATIC,
                                FieldConfig::SOURCE_MAPPED,
                                FieldConfig::SOURCE_PRODUCT_STREAM,
                                FieldConfig::SOURCE_DEFAULT,
                            ],
                        ),
                        new NotBlank(),
                    ],
                    'value' => [],
                ],
                allowExtraFields: false,
                allowMissingFields: false,
            ),
        );

        $serializer = $this->getSerializer($id, $expected);

        $existence = new EntityExistence(
            'property',
            ['id' => $id],
            true,
            false,
            false,
            []
        );

        $pair = new KeyValuePair('id', $id, false);
        $data = static::createStub(WriteParameterBag::class);

        $field = new SlotConfigField('id', 'id');
        $serializer->encode($field, $existence, $pair, $data)->current();
    }

    private function getSerializer(string $value, All $expected): SlotConfigFieldSerializer
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($value, $expected)
            ->willReturn(new ConstraintViolationList());

        return new SlotConfigFieldSerializer(
            $validator,
            static::createStub(DefinitionInstanceRegistry::class)
        );
    }
}
