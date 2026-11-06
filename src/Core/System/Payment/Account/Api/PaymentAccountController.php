<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Account\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\Account\PaymentAccountDto;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration']])]
class PaymentAccountController extends AbstractController
{
    public function __construct(private readonly PaymentAccountService $accounts)
    {
    }

    #[Route(path: '/api/_action/payment/accounts', name: 'api.action.payment.accounts', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['payment_account:read']], methods: ['GET'])]
    public function list(Context $context): JsonResponse
    {
        return new JsonResponse(['accounts' => $this->accounts->list($context)]);
    }

    #[Route(path: '/api/_action/payment/accounts/{id}', name: 'api.action.payment.account.read', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['payment_account:read']], methods: ['GET'])]
    public function read(string $id, Context $context): JsonResponse
    {
        return new JsonResponse(['account' => $this->accounts->read($id, $context)]);
    }

    #[Route(path: '/api/_action/payment/accounts', name: 'api.action.payment.account.create', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['payment_account:create']], methods: ['POST'])]
    public function create(#[MapRequestPayload] PaymentAccountDto $dto, Context $context): JsonResponse
    {
        $account = $this->accounts->save($dto, $context);

        return new JsonResponse(['account' => $this->accounts->read($account->id, $context)], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/api/_action/payment/accounts/{id}', name: 'api.action.payment.account.update', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['payment_account:update']], methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] PaymentAccountDto $dto, Context $context): JsonResponse
    {
        $this->accounts->save($dto, $context, $id);

        return new JsonResponse(['account' => $this->accounts->read($id, $context)]);
    }

    #[Route(path: '/api/_action/payment/accounts/{id}', name: 'api.action.payment.account.delete', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['payment_account:delete']], methods: ['DELETE'])]
    public function delete(string $id, Context $context): JsonResponse
    {
        $this->accounts->delete($id, $context);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
