<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Config;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal
 */
#[Package('framework')]
final class TemplateData implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public readonly bool $useDefault,
        public readonly ?string $url,
    ) {
    }
}
