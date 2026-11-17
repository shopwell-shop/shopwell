<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
final class ApprovalTaskActions
{
    public const CUSTOM = 'operation';
    public const RETURN = 'return';
    public const TRANSFER = 'transfer';
    public const ADD_SIGN = 'add-sign';
    public const OPERATIONS = [self::TRANSFER, self::ADD_SIGN, self::RETURN];
    public const EXT_CREATION_ACTION = 'core:approval-creation-action';
    public const EXT_PARTICIPATION = 'core:approval-participation';
    public const REQUIRED = 'required';
}
