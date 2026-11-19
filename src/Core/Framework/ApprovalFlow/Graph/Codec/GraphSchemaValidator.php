<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Codec;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphViolation;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\ViolationCategory;
use Shopwell\Core\Framework\Log\Package;

/**
 * No resolver protocols or file directories are registered: schema references never fetch remote input.
 * Each validation has its own loader so equal schema IDs from different declarations cannot alias cached schemas.
 *
 * @internal
 */
#[Package('framework')]
class GraphSchemaValidator
{
    /**
     * @return list<GraphViolation>
     */
    public function validate(mixed $value, string $schema, string $path = '', ViolationCategory $category = ViolationCategory::Structure): array
    {
        $parser = new SchemaParser(options: [
            'allowDefaults' => false,
            'allowMappers' => false,
            'allowFilters' => false,
            'allowPragmas' => false,
            'allowTemplates' => false,
            'allowGlobals' => false,
            'allowSlots' => false,
            'allowKeywordValidators' => false,
            'allowDataKeyword' => false,
        ]);
        $validator = new Validator(new SchemaLoader($parser), max_errors: 100, stop_at_first_error: true);
        $result = $validator->validate($value, $schema);
        $error = $result->error();
        if ($error === null) {
            return [];
        }

        return $this->collectViolations($error, $path, $category);
    }

    /**
     * @return list<GraphViolation>
     */
    private function collectViolations(ValidationError $error, string $path, ViolationCategory $category): array
    {
        if ($error->subErrors() !== []) {
            $violations = [];
            foreach ($error->subErrors() as $child) {
                array_push($violations, ...$this->collectViolations($child, $path, $category));
            }

            return $violations;
        }

        $formatter = new ErrorFormatter();
        $pointer = $formatter->formatErrorKey($error);

        return [new GraphViolation($category, 'schema_' . $error->keyword(), $path . ($error->data()->fullPath() === [] ? '' : $pointer), $formatter->formatErrorMessage($error))];
    }
}
