<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * A message that will not succeed on another try.
 *
 * This class is a marker: it carries no constant and no factory of its own, the codes and the
 * factories of the sms domain live in SmsException. What it adds is the distinction the queue
 * needs - the transports throw it for a cause the provider will refuse again (a template that was
 * never reported, a signature that is not approved, a number on a block list, a parameter the
 * template does not declare), so SendSmsHandler can turn it into an
 * UnrecoverableMessageHandlingException and the retries stop after the first attempt.
 *
 * A failure that can pass later - the endpoint not reachable, a system error at the provider, a
 * rate limit - is thrown as the plain SmsException and is retried.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SmsUnrecoverableException extends SmsException
{
}
