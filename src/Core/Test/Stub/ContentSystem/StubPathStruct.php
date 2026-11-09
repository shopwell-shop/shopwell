<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\ContentSystem;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('framework')]
class StubPathStruct extends Struct
{
    /**
     * @param array<string, mixed>|null $customFields
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?self $child = null,
        public readonly mixed $nonStructProp = null,
        public readonly ?array $customFields = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'test_path_struct';
    }
}
