<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var array<string, list<Constraint>>
     */
    private array $cachedConstraints = [];

    public function __construct(
        protected ValidatorInterface $validator,
        protected DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        return $data;
    }

    /**
     * @param list<Constraint> $constraints
     */
    protected function validate(
        array $constraints,
        KeyValuePair $data,
        string $path
    ): void {
        $violationList = new ConstraintViolationList();

        foreach ($constraints as $constraint) {
            $violations = $this->validator->validate($data->getValue(), $constraint);

            /** @var ConstraintViolation $violation */
            foreach ($violations as $violation) {
                $fieldName = $data->getKey();

                // correct pointer for json fields with pre-defined structure
                if ($violation->getPropertyPath()) {
                    $property = str_replace('][', '/', $violation->getPropertyPath());
                    $property = trim($property, '][');
                    $fieldName .= '/' . $property;
                }

                $fieldName = '/' . $fieldName;

                $violationList->add(
                    new ConstraintViolation(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getParameters(),
                        $violation->getRoot(),
                        $fieldName,
                        $violation->getInvalidValue(),
                        $violation->getPlural(),
                        $violation->getCode(),
                        $violation->getConstraint(),
                        $violation->getCause()
                    )
                );
            }
        }

        if (\count($violationList)) {
            throw DataAbstractionLayerException::invalidWriteConstraintViolation($violationList, $path);
        }
    }

    /**
     * @param mixed $value
     */
    protected function requiresValidation(
        Field $field,
        EntityExistence $existence,
        $value,
        WriteParameterBag $parameters
    ): bool {
        if ($value !== null) {
            return true;
        }

        if ($existence->isChild() && $this->isInherited($field, $parameters)) {
            return false;
        }

        if ($existence->hasEntityName()
            && $this->definitionRegistry->getByEntityName($existence->getEntityName()) instanceof EntityTranslationDefinition
            && $parameters->getCurrentWriteLanguageId() !== Defaults::LANGUAGE_SYSTEM
        ) {
            return false;
        }

        return $field->is(Required::class);
    }

    protected function isInherited(Field $field, WriteParameterBag $parameters): bool
    {
        if ($parameters->getDefinition()->isInheritanceAware()) {
            return $field->is(Inherited::class);
        }

        if (!$parameters->getDefinition() instanceof EntityTranslationDefinition) {
            return false;
        }

        $parentField = $parameters->getDefinition()->getParentDefinition()->getFields()->get($field->getPropertyName());

        return $parentField !== null && $parentField->is(Inherited::class);
    }

    protected function validateIfNeeded(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): void
    {
        if (!$this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            return;
        }

        $constraints = $this->getCachedConstraints($field);

        $this->validate($constraints, $data, $parameters->getPath());
    }

    /**
     * @return list<Constraint>
     */
    protected function getConstraints(Field $field): array
    {
        return [];
    }

    /**
     * @return list<Constraint>
     */
    protected function getCachedConstraints(Field $field): array
    {
        $key = $field->getPropertyName() . spl_object_id($field);

        if (\array_key_exists($key, $this->cachedConstraints)) {
            return $this->cachedConstraints[$key];
        }

        return $this->cachedConstraints[$key] = $this->getConstraints($field);
    }

    protected function sanitize(HtmlSanitizer $sanitizer, KeyValuePair $data, Field $field, EntityExistence $existence): ?string
    {
        if ($data->getValue() === null) {
            return null;
        }

        if (!$field->is(AllowHtml::class)) {
            return $sanitizer->stripTags((string) $data->getValue());
        }

        if ($field->getFlag(AllowHtml::class)->isSanitized()) {
            $fieldKey = \sprintf('%s.%s', (string) $existence->getEntityName(), $field->getPropertyName());

            return $sanitizer->sanitize((string) $data->getValue(), [], false, $fieldKey);
        }

        return (string) $data->getValue();
    }
}
