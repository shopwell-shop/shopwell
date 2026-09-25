<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class CurrencyValidator implements EventSubscriberInterface
{
    final public const VIOLATION_DELETE_DEFAULT_CURRENCY = 'delete_default_currency_violation';

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();

        foreach ($event->getDeletedPrimaryKeys(CurrencyDefinition::ENTITY_NAME) as $pk) {
            $id = mb_strtolower(Uuid::fromBytesToHex($pk['id']));
            if ($id !== Defaults::CURRENCY) {
                continue;
            }

            $msgTpl = 'The default currency {{ id }} cannot be deleted.';
            $parameters = ['{{ id }}' => $id];
            $msg = \sprintf('The default currency %s cannot be deleted.', $id);
            $violation = new ConstraintViolation(
                $msg,
                $msgTpl,
                $parameters,
                null,
                '/' . $id,
                $id,
                null,
                self::VIOLATION_DELETE_DEFAULT_CURRENCY
            );

            $violations->add($violation);
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }
}
