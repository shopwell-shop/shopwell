<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Subscriber;

use Doctrine\DBAL\Connection;
use Monolog\Level;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsDeliveryCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsDeliveryEntity;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsGatewayCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsGatewayEntity;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsTemplateEntity;
use Shopwell\Core\Framework\Sms\Event\SmsFailedEvent;
use Shopwell\Core\Framework\Sms\Event\SmsSentEvent;
use Shopwell\Core\Framework\Sms\Gateway\Null\NullGatewayTransportFactory;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\Framework\Sms\Struct\SmsMessageOptions;
use Shopwell\Core\Framework\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Notifier\Event\FailedMessageEvent;
use Symfony\Component\Notifier\Event\SentMessageEvent;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Records what happened to a message.
 *
 * The transports of the providers dispatch the events of the notifier on their own, so this
 * subscriber gets the success and the failure of every gateway - including the providers an
 * extension brought - for free.
 *
 * The body is not stored, only its hash, and the number is masked with a hash next to it.
 *
 * @internal
 */
#[Package('framework')]
class SmsDeliverySubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string, string|null>
     */
    private array $templateIds = [];

    /**
     * @param EntityRepository<SmsDeliveryCollection> $deliveryRepository
     * @param EntityRepository<SmsGatewayCollection> $gatewayRepository
     * @param EntityRepository<SmsTemplateCollection> $templateRepository
     */
    public function __construct(
        private readonly EntityRepository $deliveryRepository,
        private readonly EntityRepository $gatewayRepository,
        private readonly EntityRepository $templateRepository,
        private readonly PhoneNumberNormalizer $normalizer,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SentMessageEvent::class => 'onSentMessage',
            FailedMessageEvent::class => 'onFailedMessage',
        ];
    }

    public function onSentMessage(SentMessageEvent $event): void
    {
        $sentMessage = $event->getMessage();
        $message = $sentMessage->getOriginalMessage();

        if (!$message instanceof SmsMessage) {
            return;
        }

        $gatewayId = $this->gatewayIdOf($message);
        $technicalName = $this->technicalNameOf($gatewayId);

        $this->deliveryRepository->create([
            $this->record($message, $gatewayId, $technicalName, SmsDeliveryEntity::STATUS_SENT, null, $sentMessage->getMessageId()),
        ], Context::createDefaultContext());

        $this->eventDispatcher->dispatch(new SmsSentEvent(
            $message,
            $technicalName,
            $gatewayId,
            $sentMessage->getMessageId(),
            Context::createDefaultContext()
        ));
    }

    public function onFailedMessage(FailedMessageEvent $event): void
    {
        $message = $event->getMessage();

        if (!$message instanceof SmsMessage) {
            return;
        }

        $gatewayId = $this->gatewayIdOf($message);
        $technicalName = $this->technicalNameOf($gatewayId);
        $error = $event->getError();

        $this->deliveryRepository->create([
            $this->record($message, $gatewayId, $technicalName, SmsDeliveryEntity::STATUS_FAILED, $error, null),
        ], Context::createDefaultContext());

        $this->logFailure($message, $error);

        $this->eventDispatcher->dispatch(new SmsFailedEvent(
            $message,
            $technicalName,
            $gatewayId,
            $error,
            Context::createDefaultContext()
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function record(
        SmsMessage $message,
        ?string $gatewayId,
        string $technicalName,
        string $status,
        ?\Throwable $error,
        ?string $providerMessageId,
    ): array {
        $options = $message->getOptions();
        $options = $options instanceof SmsMessageOptions ? $options : null;
        $templateRef = $options?->getTemplateRef();

        $now = $this->clock->now();
        $record = [
            'id' => Uuid::randomHex(),
            'gatewayId' => $gatewayId,
            'technicalName' => $technicalName,
            'phoneMasked' => $this->normalizer->mask($message->getPhone()),
            'phoneHash' => $this->normalizer->hash($message->getPhone()),
            'countryCode' => $this->normalizer->countryCode($message->getPhone()),
            'scene' => $options?->getScene(),
            'templateId' => $templateRef === null ? null : $this->templateIdOf($templateRef),
            'templateRef' => $templateRef,
            'templateCode' => $options?->getTemplateCode(),
            // the subject of a free text message is its body, the one of a reported template is only a name
            'contentHash' => $options !== null && $options->isTemplateBased() ? null : Hasher::hash($message->getSubject()),
            'status' => $status,
            'providerMessageId' => $providerMessageId,
            'segments' => $this->normalizer->countSegments($message->getSubject()),
            'sentAt' => $status === SmsDeliveryEntity::STATUS_SENT ? $now : null,
            'failedAt' => $status === SmsDeliveryEntity::STATUS_FAILED ? $now : null,
        ];

        if ($error !== null) {
            $record['errorCode'] = $error->getCode() === 0 ? null : (string) $error->getCode();
            $record['errorMessage'] = mb_substr($error->getMessage(), 0, 512);
        }

        return $record;
    }

    private function logFailure(SmsMessage $message, \Throwable $error): void
    {
        $context = null;

        try {
            $context = json_encode([
                'error' => $error->getMessage(),
                'subject' => $message->getSubject(),
            ], \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
        }

        $extra = null;

        try {
            $extra = json_encode([
                'exception' => $error->__toString(),
                'trace' => $error->getTraceAsString(),
            ], \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
        }

        $this->connection->insert('log_entry', [
            'id' => Uuid::randomBytes(),
            'message' => 'sms.message.failed',
            'level' => Level::Error->value,
            'channel' => 'sms',
            'context' => $context,
            'extra' => $extra,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function gatewayIdOf(SmsMessage $message): ?string
    {
        $transport = $message->getTransport();

        return $transport === null || $transport === SmsGatewayResolverInterface::GATEWAY_NULL ? null : $transport;
    }

    private function technicalNameOf(?string $gatewayId): string
    {
        if ($gatewayId === null || $gatewayId === '') {
            return NullGatewayTransportFactory::TECHNICAL_NAME;
        }

        $gateway = $this->gatewayRepository
            ->search(new Criteria([$gatewayId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        return $gateway instanceof SmsGatewayEntity
            ? $gateway->technicalName
            : NullGatewayTransportFactory::TECHNICAL_NAME;
    }

    private function templateIdOf(string $templateRef): ?string
    {
        if (\array_key_exists($templateRef, $this->templateIds)) {
            return $this->templateIds[$templateRef];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $templateRef));
        $criteria->setLimit(1);

        $template = $this->templateRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        return $this->templateIds[$templateRef] = $template instanceof SmsTemplateEntity ? $template->id : null;
    }
}
