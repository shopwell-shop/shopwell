<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ActionButton\Response;

use Shopwell\Core\Framework\App\ActionButton\AppAction;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface ActionButtonResponseFactoryInterface
{
    public function supports(string $actionType): bool;

    /**
     * @param array<string, mixed> $payload
     */
    public function create(AppAction $action, array $payload, Context $context): ActionButtonResponse;
}
