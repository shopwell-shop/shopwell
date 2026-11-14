<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * The scheduler interprets lifecycle decisions; extensions interpret business results and output ports.
 */
#[Package('framework')]
final readonly class NodeOutcome
{
    /**
     * @param array<string, mixed> $output
     */
    private function __construct(
        private string $type,
        public ?string $port = null,
        public array $output = [],
        public ?string $waitingFor = null,
        public ?string $result = null,
    ) {
    }

    /**
     * @param array<string, mixed> $output
     */
    public static function proceed(?string $port = null, array $output = []): self
    {
        return new self('continue', port: $port, output: $output);
    }

    public static function wait(string $signal): self
    {
        if ($signal === '') {
            throw WorkflowExecutionException::invalidSignal($signal);
        }

        return new self('wait', waitingFor: $signal);
    }

    public static function complete(string $result = 'completed'): self
    {
        return new self('complete', result: $result);
    }

    public function isWaiting(): bool
    {
        return $this->type === 'wait';
    }

    public function isContinue(): bool
    {
        return $this->type === 'continue';
    }

    public function isComplete(): bool
    {
        return $this->type === 'complete';
    }
}
