<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Requirement;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Requirement\Exception\RequirementException;
use Shopwell\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;

#[Package('framework')]
class RequirementExceptionStack
{
    /**
     * @var RequirementException[]
     */
    private array $exceptions = [];

    public function add(RequirementException ...$exceptions): void
    {
        foreach ($exceptions as $exception) {
            $this->exceptions[] = $exception;
        }
    }

    public function tryToThrow(string $method): void
    {
        $exceptions = $this->exceptions;
        $this->exceptions = [];

        if ($exceptions) {
            throw new RequirementStackException($method, ...$exceptions);
        }
    }
}
