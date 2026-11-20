<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * Describes a business type without granting submission or execution authority.
 */
#[Package('framework')]
final readonly class ApprovalBusinessTypeSpecification
{
    public string $formSchemaId;

    public string $formSchemaHash;

    public function __construct(
        public string $type,
        public string $version,
        public string $labelSnippet,
        public string $source,
        public string $formSchema,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-zA-Z0-9_-]*$/D', $type)) {
            throw ApprovalFlowException::invalidBusinessType($source, 'type must be a namespaced identifier');
        }
        if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) {
            throw ApprovalFlowException::invalidBusinessType($source, 'version must be an exact major.minor.patch version');
        }
        try {
            $schema = json_decode($formSchema, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ApprovalFlowException::invalidBusinessType($source, 'formSchema is not valid JSON', $exception);
        }
        $id = $schema instanceof \stdClass ? ($schema->{'$id'} ?? null) : null;
        if (!$schema instanceof \stdClass || ($schema->type ?? null) !== 'object' || !\is_string($id) || trim($id) === '') {
            throw ApprovalFlowException::invalidBusinessType($source, 'formSchema must declare an object and a nonempty $id');
        }

        // Keep the authored bytes as the schema pin, including JSON map/list distinctions.
        $this->formSchemaId = $id;
        $this->formSchemaHash = Hasher::hash($formSchema, 'sha256');
    }
}
