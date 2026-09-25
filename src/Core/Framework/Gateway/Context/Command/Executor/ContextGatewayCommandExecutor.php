<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command\Executor;

use Shopwell\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopwell\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopwell\Core\Framework\Gateway\Context\Command\TokenCommandInterface;
use Shopwell\Core\Framework\Gateway\GatewayException;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class ContextGatewayCommandExecutor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly ContextGatewayCommandRegistry $registry,
        private readonly ContextGatewayCommandValidator $commandValidator,
        private readonly ExceptionLogger $logger,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
    ) {
    }

    public function execute(ContextGatewayCommandCollection $commands, SalesChannelContext $context): ContextTokenResponse
    {
        $this->commandValidator->validate($commands, $context);

        $parameters = [];

        if ($tokenCommand = $commands->getSingleTokenCommand()) {
            $this->registry->get($tokenCommand::COMMAND_KEY)->handle($tokenCommand, $context, $parameters);

            $token = $parameters['token'];
            unset($parameters['token']);

            $contextParameters = new SalesChannelContextServiceParameters($context->getSalesChannelId(), $token);
            $context = $this->salesChannelContextService->get($contextParameters);
        }

        foreach ($commands as $command) {
            // these commands are already handled
            if ($command instanceof TokenCommandInterface) {
                continue;
            }

            if (!$this->registry->has($command::getDefaultKeyName())) {
                $this->logger->logOrThrowException(GatewayException::handlerNotFound($command::getDefaultKeyName()));
                continue;
            }

            $this->registry->get($command::getDefaultKeyName())->handle($command, $context, $parameters);
        }

        $response = new ContextTokenResponse($context->getToken());
        $response->headers->remove(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if ($parameters !== []) {
            $response = $this->contextSwitchRoute->switchContext(new RequestDataBag($parameters), $context);
        }

        if ($tokenCommand) {
            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());
        }

        return $response;
    }
}
