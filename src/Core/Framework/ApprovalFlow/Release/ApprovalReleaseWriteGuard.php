<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Release;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Release\ApprovalReleaseServiceTest
 */
#[Package('framework')]
final class ApprovalReleaseWriteGuard implements EventSubscriberInterface
{
    public const WRITE_SCOPE = 'approval-flow-release-insert';

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validateWrite'];
    }

    public function validateWrite(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== ApprovalReleaseDefinition::ENTITY_NAME) {
                continue;
            }
            if ($event->getContext()->getScope() !== self::WRITE_SCOPE || !$command instanceof InsertCommand) {
                throw ApprovalFlowException::uncontrolledReleaseWrite();
            }
        }
    }
}
