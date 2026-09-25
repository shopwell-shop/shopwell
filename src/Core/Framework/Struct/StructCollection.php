<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Struct;

use Shopwell\Core\Framework\Log\Package;

/**
 * @template TElement of Struct
 *
 * @extends Collection<TElement>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class StructCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'dal_struct_collection';
    }

    /**
     * @return class-string<Struct>
     */
    protected function getExpectedClass(): ?string
    {
        return Struct::class;
    }
}
