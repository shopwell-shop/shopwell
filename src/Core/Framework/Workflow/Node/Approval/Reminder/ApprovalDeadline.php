<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalDeadline
{
    private function __construct(
        public int $minutes,
        public bool $remind,
        public bool $workingTime = false,
        public ?string $escalateTo = null,
    ) {
    }

    public static function fromConfiguration(mixed $configuration): self
    {
        if ($configuration instanceof \stdClass) {
            $configuration = get_object_vars($configuration);
        }

        if (!\is_array($configuration)
            || array_diff(array_keys($configuration), ['minutes', 'remind', 'workingTime', 'escalateTo']) !== []
            || !\is_int($configuration['minutes'] ?? null)
            || $configuration['minutes'] < 1
            || $configuration['minutes'] > 525600
            || !\is_bool($configuration['remind'] ?? null)) {
            throw WorkflowExecutionException::invalidGraph('An approval deadline requires 1 to 525600 elapsed minutes and a boolean reminder choice.');
        }

        if (isset($configuration['workingTime']) && !\is_bool($configuration['workingTime'])) {
            throw WorkflowExecutionException::invalidGraph('The workingTime deadline setting must be boolean.');
        }

        $escalateTo = $configuration['escalateTo'] ?? null;
        if ($escalateTo !== null && (!\is_string($escalateTo) || !Uuid::isValid($escalateTo))) {
            throw WorkflowExecutionException::invalidGraph('The escalateTo deadline setting must be a valid user id.');
        }

        return new self($configuration['minutes'], $configuration['remind'], $configuration['workingTime'] ?? false, $escalateTo);
    }

    /**
     * @return array{minutes: int, remind: bool, workingTime?: bool, escalateTo?: string}
     */
    public function configuration(): array
    {
        $configuration = ['minutes' => $this->minutes, 'remind' => $this->remind];
        if ($this->workingTime) {
            $configuration['workingTime'] = true;
        }
        if ($this->escalateTo !== null) {
            $configuration['escalateTo'] = $this->escalateTo;
        }

        return $configuration;
    }

    public function dueAt(\DateTimeInterface $createdAt): \DateTimeImmutable
    {
        $start = \DateTimeImmutable::createFromInterface($createdAt)->setTimezone(new \DateTimeZone('UTC'));
        if (!$this->workingTime) {
            return $start->add(new \DateInterval('PT' . $this->minutes . 'M'));
        }
        $remaining = $this->minutes;
        while ($remaining > 0) {
            $hour = (int) $start->format('G');
            if ((int) $start->format('N') <= 5 && $hour >= 9 && $hour < 18) {
                $available = min($remaining, (18 * 60) - ($hour * 60 + (int) $start->format('i')));
                $start = $start->add(new \DateInterval('PT' . $available . 'M'));
                $remaining -= $available;
                continue;
            }
            $start = $start->modify('next weekday')->setTime(9, 0);
        }

        return $start;
    }
}
