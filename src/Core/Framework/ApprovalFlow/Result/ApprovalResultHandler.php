<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Applies a committed result to its original business submission.
 * Handlers must commit their business changes and deduplication record together before returning.
 */
#[Package('framework')]
interface ApprovalResultHandler
{
    public function getType(): string;

    public function getVersion(): string;

    public function handle(ApprovalResult $result, ApprovalRequestEntity $request, Context $context): void;
}
