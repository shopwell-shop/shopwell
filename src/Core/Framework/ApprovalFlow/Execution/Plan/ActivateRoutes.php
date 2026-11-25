<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Plan;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ActivateRoutes implements ExecutionChange
{
    /**
     * @var non-empty-list<array{connectionId: string, nextNodeId: string}>
     */
    public array $routes;

    /**
     * @param non-empty-list<array{connectionId: string, nextNodeId: string}> $routes
     */
    public function __construct(array $routes)
    {
        $this->routes = array_map(static fn (array $route): array => [
            'connectionId' => $route['connectionId'],
            'nextNodeId' => $route['nextNodeId'],
        ], $routes);
    }
}
