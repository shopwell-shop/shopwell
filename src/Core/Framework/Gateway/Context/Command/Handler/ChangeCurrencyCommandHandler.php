<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command\Handler;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Shopwell\Core\Framework\Gateway\GatewayException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<ChangeCurrencyCommand>
 *
 * @internal
 */
#[Package('framework')]
class ChangeCurrencyCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(
        private readonly EntityRepository $currencyRepository,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', $command->iso));

        $currencyId = $this->currencyRepository->searchIds($criteria, $context->getContext())->firstId();

        if ($currencyId === null) {
            throw GatewayException::handlerException('Currency with iso code {{ isoCode }} not found', ['isoCode' => $command->iso]);
        }

        $parameters['currencyId'] = $currencyId;
    }

    public static function supportedCommands(): array
    {
        return [ChangeCurrencyCommand::class];
    }
}
