<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopwell\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Json;
use Shopwell\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class VariantListingConfigFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ValidatorInterface $validator
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw DataAbstractionLayerException::invalidSerializerField(self::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();
        $value['displayParent'] = isset($value['displayParent']) ? (int) $value['displayParent'] : null;

        yield $field->getStorageName() => $value !== [] ? Json::encode($value) : null;
    }

    public function decode(Field $field, mixed $value): ?VariantListingConfig
    {
        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        return new VariantListingConfig(
            isset($value['displayParent']) ? (bool) $value['displayParent'] : null,
            $value['mainVariantId'] ?? null,
            $value['configuratorGroupConfig'] ?? null,
        );
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Collection(
                fields: [
                    'displayParent' => [new Type('boolean')],
                    'mainVariantId' => [new Uuid()],
                    'configuratorGroupConfig' => [
                        new Optional(
                            new Collection(
                                fields: [
                                    'id' => [new NotBlank(), new Uuid()],
                                    'representation' => [new NotBlank(), new Type('string')],
                                    'expressionForListings' => [new NotBlank(), new Type('boolean')],
                                ],
                                allowExtraFields: true,
                                allowMissingFields: true
                            )
                        ),
                    ],
                ],
                allowExtraFields: true,
                allowMissingFields: true
            ),
        ];
    }
}
