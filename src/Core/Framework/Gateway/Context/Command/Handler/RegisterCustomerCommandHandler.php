<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command\Handler;

use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopwell\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<RegisterCustomerCommand>
 *
 * @internal
 */
#[Package('framework')]
class RegisterCustomerCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractRegisterRoute $registerRoute,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $data = new RequestDataBag($command->data);
        $response = $this->registerRoute->register($data, $context);

        $parameters['token'] = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
    }

    public static function supportedCommands(): array
    {
        return [RegisterCustomerCommand::class];
    }
}
