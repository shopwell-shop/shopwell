<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command\Executor;

use Shopwell\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopwell\Core\Framework\Gateway\GatewayException;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class ContextGatewayCommandValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function validate(ContextGatewayCommandCollection $commands, SalesChannelContext $context): void
    {
        if ($commands->getTokenCommands()->count() > 1) {
            $this->logger->logOrThrowException(GatewayException::commandValidationFailed('Only one register or login command is allowed'));

            return;
        }

        $types = $commands->getCommandTypes();

        if (\count($types) !== \count(\array_unique($types))) {
            $this->logger->logOrThrowException(GatewayException::commandValidationFailed('Duplicate commands of a type are not allowed'));
        }
    }
}
