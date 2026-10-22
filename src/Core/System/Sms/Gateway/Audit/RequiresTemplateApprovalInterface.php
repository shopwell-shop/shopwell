<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Audit;

use Shopwell\Core\Framework\Log\Package;

/**
 * Declares that this provider delivers a message only once its template passed the audit of the
 * provider.
 *
 * Implementing the interface is the declaration - there is no method that could answer false, a
 * provider that does not need an audit simply does not implement it. The core asks the registry,
 * never a name: the routing skips every gateway whose provider declares the interface but has no
 * approved mapping, and the administration marks those rows as "approval needed".
 *
 * This is deliberately independent of the other two audit capabilities: a provider whose templates
 * are reported at its console only implements this one, and the administration then offers no
 * submission and no refresh, but keeps the state editable by hand.
 */
#[Package('framework')]
interface RequiresTemplateApprovalInterface
{
}
