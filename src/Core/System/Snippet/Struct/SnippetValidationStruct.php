<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SnippetValidationStruct extends Struct
{
    public function __construct(
        public readonly MissingSnippetCollection $missingSnippets,
        public readonly InvalidPluralizationCollection $invalidPluralization,
    ) {
    }
}
