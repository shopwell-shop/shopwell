<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Login;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Puts the OAuth2 login channels the shop offers onto the pages that render them, so the
 * templates show exactly the buttons the admin enabled and configured.
 *
 * The login and register page get the channels to log in with, the profile page gets the channels
 * plus the ones the signed in customer already linked.
 *
 * @internal
 */
#[Package('framework')]
class OAuth2LoginPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OAuth2LoginService $loginService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AccountLoginPageLoadedEvent::class => 'addChannels',
            AccountProfilePageLoadedEvent::class => 'addCustomerBindings',
        ];
    }

    public function addChannels(AccountLoginPageLoadedEvent $event): void
    {
        $channels = $this->loginService->getEnabledChannels($event->getSalesChannelContext()->getContext());

        $event->getPage()->addArrayExtension('oauth2Login', [
            'channels' => $this->mapChannels($channels),
        ]);
    }

    public function addCustomerBindings(AccountProfilePageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();

        $linked = $customer === null
            ? []
            : array_keys($this->loginService->getCustomerIdentities($customer->getId(), $salesChannelContext->getContext()));

        $channels = $this->loginService->getEnabledChannels($salesChannelContext->getContext());

        $event->getPage()->addArrayExtension('oauth2Login', [
            'channels' => $this->mapChannels($channels),
            'linked' => $linked,
        ]);
    }

    /**
     * @param array<string, OAuth2Channel> $channels
     *
     * @return list<array{technicalName: string, labelSnippet: string, icon: string|null}>
     */
    private function mapChannels(array $channels): array
    {
        return array_values(array_map(
            static fn (OAuth2Channel $channel): array => [
                'technicalName' => $channel->technicalName,
                'labelSnippet' => $channel->labelSnippet,
                'icon' => $channel->icon,
            ],
            $channels
        ));
    }
}
