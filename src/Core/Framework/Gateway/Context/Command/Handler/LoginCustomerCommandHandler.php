<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command\Handler;

use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<LoginCustomerCommand>
 *
 * @internal
 */
#[Package('framework')]
class LoginCustomerCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $customer = $this->accountService->getCustomerByEmail($command->customerEmail, $context);
        $parameters['token'] = $this->accountService->loginById($customer->getId(), $context);
    }

    public static function supportedCommands(): array
    {
        return [LoginCustomerCommand::class];
    }
}
