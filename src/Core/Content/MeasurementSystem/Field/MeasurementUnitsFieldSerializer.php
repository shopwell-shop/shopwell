<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MeasurementSystem\Field;

use Shopwell\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopwell\Core\Content\MeasurementSystem\MeasurementUnitTypeEnum;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('inventory')]
class MeasurementUnitsFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if ($data->getValue() === null) {
            $defaultUnits = MeasurementUnits::createDefaultUnits();

            $data->setValue([
                'system' => $defaultUnits->getSystem(),
                'units' => $defaultUnits->getUnits(),
            ]);
        } elseif ($data->getValue() instanceof MeasurementUnits) {
            $measurementUnits = $data->getValue();

            $data->setValue([
                'system' => $measurementUnits->getSystem(),
                'units' => $measurementUnits->getUnits(),
            ]);
        }

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): MeasurementUnits
    {
        $defaultUnits = MeasurementUnits::createDefaultUnits();

        if ($value === null) {
            return $defaultUnits;
        }

        $decoded = parent::decode($field, $value);
        if (!\is_array($decoded)) {
            return $defaultUnits;
        }
        $system = $decoded['system'] ?? $defaultUnits->getSystem();
        $units = !empty($decoded['units']) ? array_merge($defaultUnits->getUnits(), $decoded['units']) : $defaultUnits->getUnits();

        return new MeasurementUnits($system, $units);
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Type('array'),
            new NotNull(),
            new Collection(
                fields: [
                    'system' => [new NotBlank(), new Type('string')],
                    'units' => [
                        new Type('array'),
                        new Collection(
                            fields: [
                                MeasurementUnitTypeEnum::LENGTH->value => [new Type('string'), new NotNull()],
                                MeasurementUnitTypeEnum::WEIGHT->value => [new Type('string'), new NotNull()],
                            ],
                            allowExtraFields: true,
                            allowMissingFields: false
                        ),
                    ],
                ],
                allowExtraFields: true,
                allowMissingFields: false
            ),
        ];
    }
}
