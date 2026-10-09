<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Subscriber;

use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerTokenSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly RequestStack $requestStack,
        private readonly SessionContextTokenAccessor $sessionContextToken
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
            CustomerEvents::CUSTOMER_DELETED_EVENT => 'onCustomerDeleted',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getResults()->only(EntityWriteResult::OPERATION_UPDATE) as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!$this->customerCredentialsChanged($payload)) {
                continue;
            }

            $customerId = $payload['id'];
            $newToken = $this->invalidateUsingSession($customerId);

            if ($newToken) {
                $this->contextPersister->revokeAllCustomerTokens($customerId, $newToken);
            } else {
                $this->contextPersister->revokeAllCustomerTokens($customerId);
            }
        }
    }

    public function onCustomerDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $customerId) {
            $this->contextPersister->revokeAllCustomerTokens($customerId);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function customerCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']);
    }

    private function invalidateUsingSession(string $customerId): ?string
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if ($mainRequest === null) {
            return null;
        }

        $context = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        // Not a storefront request
        if (!$context instanceof SalesChannelContext) {
            return null;
        }

        // The context customer is not the same as logged-in. We don't modify the user session
        if ($context->getCustomerId() !== $customerId) {
            return null;
        }

        $newToken = $this->contextPersister->replace(
            $context->getToken(),
            $context,
        );

        $context->assign([
            'token' => $newToken,
        ]);

        // a request without a session of its own gets every token revoked
        if (!$this->sessionContextToken->rotate($mainRequest, $context->getSalesChannelId(), $newToken)) {
            return null;
        }

        return $newToken;
    }
}
