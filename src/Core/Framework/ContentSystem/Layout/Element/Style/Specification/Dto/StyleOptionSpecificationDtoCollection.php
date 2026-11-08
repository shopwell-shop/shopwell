<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Validation wrapper for a set of style option declarations. Keyed by option name (e.g.
 * `col-span`) so Symfony includes the name in violation property paths: options[col-span].type
 *
 * @internal
 */
#[Package('framework')]
final readonly class StyleOptionSpecificationDtoCollection
{
    /**
     * @param array<string, StyleOptionSpecificationDto> $options
     */
    public function __construct(
        #[Assert\Valid]
        public array $options,
    ) {
    }
}
