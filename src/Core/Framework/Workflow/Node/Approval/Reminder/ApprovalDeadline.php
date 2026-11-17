<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalDeadline
{
    private function __construct(public int $minutes, public bool $remind)
    {
    }

    public static function fromConfiguration(mixed $configuration): self
    {
        if ($configuration instanceof \stdClass) {
            $configuration = get_object_vars($configuration);
        }

        if (!\is_array($configuration)
            || array_diff(array_keys($configuration), ['minutes', 'remind']) !== []
            || !\is_int($configuration['minutes'] ?? null)
            || $configuration['minutes'] < 1
            || $configuration['minutes'] > 525600
            || !\is_bool($configuration['remind'] ?? null)) {
            throw WorkflowExecutionException::invalidGraph('An approval deadline requires 1 to 525600 elapsed minutes and a boolean reminder choice.');
        }

        return new self($configuration['minutes'], $configuration['remind']);
    }

    /**
     * @return array{minutes: int, remind: bool}
     */
    public function configuration(): array
    {
        return ['minutes' => $this->minutes, 'remind' => $this->remind];
    }

    public function dueAt(\DateTimeInterface $createdAt): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($createdAt)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->add(new \DateInterval('PT' . $this->minutes . 'M'));
    }
}
