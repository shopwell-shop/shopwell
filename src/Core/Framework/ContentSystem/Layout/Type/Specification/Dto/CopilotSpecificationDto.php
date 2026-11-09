<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
final readonly class CopilotSpecificationDto
{
    /**
     * @param list<string> $hints
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $summary,
        public array $hints,
    ) {
    }

    public function toCopilotSpecification(): CopilotSpecification
    {
        return new CopilotSpecification($this->summary, $this->hints);
    }
}
