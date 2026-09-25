<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LoginRoute extends AbstractLoginRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function getDecorated(): AbstractLoginRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/login', name: 'store-api.account.login', methods: ['POST'])]
    public function login(#[\SensitiveParameter] RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        EmailIdnConverter::encodeDataBag($data);
        $email = (string) $data->get('email', $data->get('username'));

        $combinedKey = null;
        $clientIpKey = null;
        $emailKey = null;

        if ($this->requestStack->getMainRequest() !== null) {
            $clientIpKey = (string) $this->requestStack->getMainRequest()->getClientIp();
            $emailKey = strtolower($email);
            $combinedKey = $emailKey . '-' . $clientIpKey;

            try {
                $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $combinedKey);
                $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::LOGIN_USER, $emailKey);
                $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::LOGIN_CLIENT, $clientIpKey);
            } catch (RateLimitExceededException $exception) {
                throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
            }
        }

        $token = $this->accountService->loginByCredentials(
            $email,
            (string) $data->get('password'),
            $context
        );

        if ($combinedKey !== null) {
            $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $combinedKey);
        }

        if ($clientIpKey !== null) {
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_CLIENT, $clientIpKey);
        }

        if ($emailKey !== null) {
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_USER, $emailKey);
        }

        return new ContextTokenResponse($token);
    }
}
