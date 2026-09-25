<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('checkout')]
class DateIntervalFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw DataAbstractionLayerException::invalidSerializerField(self::class, $field);
        }

        $interval = $data->getValue();

        if ($interval === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if (\is_string($interval)) {
            try {
                $interval = new DateInterval($interval);
            } catch (\Throwable $e) {
                throw DataAbstractionLayerException::invalidDateIntervalFormat($interval, $e);
            }
        }

        $data->setValue($interval);
        $this->validateIfNeeded($field, $existence, $data, $parameters);

        if (!$interval instanceof \DateInterval) {
            yield $field->getStorageName() => null;

            return;
        }

        if (!$interval instanceof DateInterval) {
            yield $field->getStorageName() => (string) DateInterval::createFromDateInterval($interval);

            return;
        }

        yield $field->getStorageName() => (string) $interval;
    }

    /**
     * @param string|null $value
     */
    public function decode(Field $field, $value): ?DateInterval
    {
        if ($value === null) {
            return null;
        }

        try {
            $dateInterval = new DateInterval($value);
        } catch (\Throwable $e) {
            throw DataAbstractionLayerException::invalidDateIntervalFormat($value, $e);
        }

        return $dateInterval;
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Type(\DateInterval::class),
            new NotNull(),
        ];
    }
}
