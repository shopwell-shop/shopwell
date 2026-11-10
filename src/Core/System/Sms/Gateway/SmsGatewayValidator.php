<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Provider\Config\SmsConfigValidator;
use Shopwell\Core\System\Sms\Provider\SmsProviderRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Sms\Gateway\SmsGatewayValidatorTest
 */
#[Package('framework')]
final class SmsGatewayValidator implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<SmsGatewayCollection> $gateways
     */
    public function __construct(
        private readonly EntityRepository $gateways,
        private readonly SmsProviderRegistry $providers,
        private readonly SmsConfigValidator $validator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validate'];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommandsForEntity(SmsGatewayEntity::ENTITY_NAME) as $command) {
            if ($command instanceof DeleteCommand) {
                continue;
            }

            $payload = $command->getPayload();
            $gateway = null;

            if ($command instanceof UpdateCommand) {
                $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
                $gateway = $this->gateways->search(new Criteria([$id]), $event->getContext())->first();
            }

            // Disabled rows may be kept while credentials are being configured or a plugin is removed.
            if (!(bool) ($payload['enabled'] ?? $gateway->enabled ?? false)) {
                continue;
            }

            $provider = $this->providers->get($payload['technical_name'] ?? $gateway->technicalName ?? '');
            $credentials = \array_key_exists('credentials', $payload)
                ? json_decode($payload['credentials'] ?? '{}', true, flags: \JSON_THROW_ON_ERROR)
                : $gateway?->credentials;
            $options = \array_key_exists('options', $payload)
                ? json_decode($payload['options'] ?? '{}', true, flags: \JSON_THROW_ON_ERROR)
                : $gateway?->options;

            $this->validator->validate($provider, $credentials ?? [], $options ?? []);
        }
    }
}
