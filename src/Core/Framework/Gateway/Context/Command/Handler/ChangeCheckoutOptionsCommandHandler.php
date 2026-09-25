<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command\Handler;

use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Shopwell\Core\Framework\Gateway\GatewayException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<ChangeShippingMethodCommand|ChangePaymentMethodCommand>
 *
 * @internal
 */
#[Package('framework')]
class ChangeCheckoutOptionsCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $technicalName = $command->technicalName;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        if ($command instanceof ChangeShippingMethodCommand) {
            $shippingMethodId = $this->shippingMethodRepository->searchIds($criteria, $context->getContext())->firstId();

            if ($shippingMethodId === null) {
                throw GatewayException::handlerException('Shipping method with technical name {{ technicalName }} not found', ['technicalName' => $technicalName]);
            }

            $parameters['shippingMethodId'] = $shippingMethodId;
        }

        if ($command instanceof ChangePaymentMethodCommand) {
            $paymentMethodId = $this->paymentMethodRepository->searchIds($criteria, $context->getContext())->firstId();

            if ($paymentMethodId === null) {
                throw GatewayException::handlerException('Payment method with technical name {{ technicalName }} not found', ['technicalName' => $technicalName]);
            }

            $parameters['paymentMethodId'] = $paymentMethodId;
        }
    }

    public static function supportedCommands(): array
    {
        return [
            ChangeShippingMethodCommand::class,
            ChangePaymentMethodCommand::class,
        ];
    }
}
