<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Attribute;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class State extends Field
{
    public const TYPE = 'state';

    /**
     * @param array<string> $scopes
     */
    public function __construct(
        public string $machine,
        public array $scopes = [Context::SYSTEM_SCOPE],
        bool|array $api = false,
        ?string $column = null
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }
}
