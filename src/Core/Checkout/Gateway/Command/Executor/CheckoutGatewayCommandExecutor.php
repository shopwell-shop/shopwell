<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Command\Executor;

use Shopwell\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\CheckoutGatewayCommandCollection;
use Shopwell\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
final readonly class CheckoutGatewayCommandExecutor
{
    /**
     * @internal
     */
    public function __construct(
        private CheckoutGatewayCommandRegistry $registry,
        private ExceptionLogger $logger,
    ) {
    }

    public function execute(
        CheckoutGatewayCommandCollection $commands,
        CheckoutGatewayResponse $response,
        SalesChannelContext $context,
    ): CheckoutGatewayResponse {
        foreach ($commands as $command) {
            if (!$this->registry->has($command::getDefaultKeyName())) {
                $this->logger->logOrThrowException(CheckoutGatewayException::handlerNotFound($command::getDefaultKeyName()));
                continue;
            }

            $this->registry->get($command::getDefaultKeyName())->handle($command, $response, $context);
        }

        return $response;
    }
}
