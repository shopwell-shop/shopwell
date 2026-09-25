<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class DataMappingEvent extends Event implements ShopwellEvent
{
    /**
     * @param array<string, mixed> $output
     */
    public function __construct(
        private readonly DataBag $input,
        private array $output,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getInput(): DataBag
    {
        return $this->input;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * @param array<string, mixed> $output
     */
    public function setOutput(array $output): void
    {
        $this->output = $output;
    }
}
