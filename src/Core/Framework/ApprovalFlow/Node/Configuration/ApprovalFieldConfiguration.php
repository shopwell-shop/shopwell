<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Configuration;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalFieldConfiguration implements \JsonSerializable
{
    public const HIDDEN = 'hidden';

    public const READ_ONLY = 'readOnly';

    public const EDITABLE = 'editable';

    /**
     * @param self::HIDDEN|self::READ_ONLY|self::EDITABLE $access
     */
    public function __construct(
        public string $field,
        public string $access,
        public bool $required,
    ) {
    }

    /**
     * @return array{field: string, access: self::HIDDEN|self::READ_ONLY|self::EDITABLE, required: bool}
     */
    public function jsonSerialize(): array
    {
        return ['field' => $this->field, 'access' => $this->access, 'required' => $this->required];
    }
}
