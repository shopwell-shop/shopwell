<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Transfer\PaymentTransferApprovalStoreTest
 */
#[Package('framework')]
final class PaymentTransferWriteProtection implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validate'];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!\in_array($command->getEntityName(), ['payment_transfer', 'payment_transfer_approval', 'payment_transfer_approval_request', 'payment_transfer_approval_result'], true)) {
                continue;
            }
            // Scope alone is insufficient: the generic transition route temporarily uses system scope.
            if (!$event->getContext()->getSource() instanceof SystemSource || $command instanceof DeleteCommand) {
                throw PaymentTransferApprovalException::blocked('protected_transfer');
            }
            if (!$command instanceof UpdateCommand) {
                continue;
            }
            if ($command->getEntityName() !== 'payment_transfer'
                || array_intersect(array_keys($command->getPayload()), ['payment_app_id', 'account_id', 'recipient_id', 'out_no', 'transfer_no', 'amount', 'currency', 'provider', 'channel', 'channel_params']) !== []) {
                throw PaymentTransferApprovalException::blocked('immutable_transfer');
            }
        }
    }
}
