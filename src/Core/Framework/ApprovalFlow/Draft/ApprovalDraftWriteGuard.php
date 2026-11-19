<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Draft;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Definition\ApprovalFlowDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Draft\ApprovalDraftServiceTest
 */
#[Package('framework')]
final class ApprovalDraftWriteGuard implements EventSubscriberInterface
{
    public const WRITE_SCOPE = 'approval-flow-draft-write';

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validateWrite'];
    }

    public function validateWrite(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() === self::WRITE_SCOPE) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if (\in_array($command->getEntityName(), [ApprovalFlowDefinition::ENTITY_NAME, ApprovalDraftDefinition::ENTITY_NAME], true)) {
                throw ApprovalFlowException::uncontrolledDraftWrite();
            }
        }
    }
}
