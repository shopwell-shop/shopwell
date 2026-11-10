<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\Audit\SubmitsTemplatesForApprovalInterface;

/**
 * @internal
 */
#[Package('framework')]
abstract class SubmittingSmsProvider extends AbstractSmsProvider implements SubmitsTemplatesForApprovalInterface
{
}
