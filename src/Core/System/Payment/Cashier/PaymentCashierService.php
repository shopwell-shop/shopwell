<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Cashier;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStarter;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;
use Symfony\Component\Clock\ClockInterface;

/**
 * A cashier freezes business terms before a payer chooses a method. Routing owns merchant selection.
 *
 * @internal
 */
#[Package('framework')]
class PaymentCashierService
{
    /**
     * @param EntityRepository<PaymentCashierConfigCollection> $configs
     * @param EntityRepository<PaymentCashierSessionCollection> $sessions
     */
    public function __construct(
        private readonly EntityRepository $configs,
        private readonly EntityRepository $sessions,
        private readonly PaymentRouteResolver $routes,
        private readonly PaymentMethodRegistry $methods,
        private readonly PaymentOrderService $orders,
        private readonly PaymentOrderStarter $starter,
        private readonly PaymentOrderPresenter $presenter,
        private readonly ClockInterface $clock,
        private readonly PaymentCashierSessionLock $lock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload authenticated business parameters, without a selected method/product
     */
    public function create(PaymentAppEntity $app, string $profile, array $payload, PaymentContext $context): PaymentCashierSessionEntity
    {
        // A session validates the business obligation without opening a payment order.
        $request = PaymentOrderRequest::fromArray(array_replace($payload, ['currency' => $context->currency]));
        $business = [
            'out_trade_no' => $request->outTradeNo,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'subject' => $request->subject,
            'body' => $request->body,
            'attach' => $request->attach,
            'notify_url' => $request->notifyUrl,
            'return_url' => $request->returnUrl,
            'client_ip' => $request->clientIp,
        ];
        $snapshot = $this->snapshotContext($context);
        $config = $this->config($app, $profile);
        $existing = $this->findByTrade($app, $request->outTradeNo);
        if ($existing !== null) {
            return $this->replay($existing, $config, $business, $snapshot);
        }

        $id = Uuid::randomHex();
        $minutes = $request->expireMinutes ?? PaymentOrderService::DEFAULT_EXPIRE_MINUTES;
        if ($minutes < 1 || $minutes > 1440) {
            throw PaymentCashierException::conflict($request->outTradeNo);
        }

        try {
            $this->sessions->create([[
                'id' => $id,
                'paymentAppId' => $app->id,
                'cashierConfigId' => $config->id,
                'outTradeNo' => $request->outTradeNo,
                'payload' => $business,
                'paymentContext' => $snapshot,
                'allowedMethods' => $config->allowedMethods ?? [],
                'routingPolicy' => $config->routingPolicy,
                'presentation' => $config->presentation,
                'expireAt' => $this->clock->now()->modify('+' . $minutes . ' minutes'),
            ]], Context::createDefaultContext());
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findByTrade($app, $request->outTradeNo);
            if ($existing === null) {
                throw PaymentCashierException::conflict($request->outTradeNo);
            }

            return $this->replay($existing, $config, $business, $snapshot);
        }

        return $this->get($app, $id);
    }

    public function get(PaymentAppEntity $app, string $id): PaymentCashierSessionEntity
    {
        $session = $this->sessions->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        if (!$session instanceof PaymentCashierSessionEntity || $session->paymentAppId !== $app->id) {
            throw PaymentCashierException::unavailable($id);
        }
        if ($session->expireAt <= $this->clock->now()) {
            throw PaymentCashierException::expired($id);
        }

        return $session;
    }

    /**
     * @return list<array{method: string, translation_key: string, interaction: string}>
     */
    public function options(PaymentAppEntity $app, string $profile, PaymentContext $context): array
    {
        $config = $this->config($app, $profile);
        $options = [];
        foreach ($this->routes->options($app, $context, allowedMethods: $config->allowedMethods ?? [], policyName: $config->routingPolicy) as $route) {
            $method = $this->methods->get($route->config->product->method);
            $options[] = ['method' => $method->technicalName, 'translation_key' => $method->translationKey, 'interaction' => $route->interaction];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PaymentAppEntity $app, PaymentCashierSessionEntity $session): array
    {
        $options = [];
        foreach ($this->routes->options($app, $this->context($session), allowedMethods: $session->allowedMethods, policyName: $session->routingPolicy) as $route) {
            $method = $this->methods->get($route->config->product->method);
            $options[] = ['method' => $method->technicalName, 'translation_key' => $method->translationKey, 'interaction' => $route->interaction];
        }

        return [
            'session_id' => $session->id,
            'out_trade_no' => $session->outTradeNo,
            'amount' => $session->payload['amount'],
            'currency' => $session->payload['currency'],
            'subject' => $session->payload['subject'],
            'expires_at' => $session->expireAt->format(\DATE_ATOM),
            'presentation' => $session->presentation,
            'methods' => $options,
            'selected_method' => $session->selectedMethod,
            'payment_order' => $session->paymentOrderId === null ? null : $this->presenter->order($this->orders->getById($session->paymentOrderId)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pay(PaymentAppEntity $app, string $id, string $method): array
    {
        // Commit the selected method and order before a network call. The starter owns attempt concurrency.
        [$order, $route] = $this->lock->transaction($id, function () use ($app, $id, $method): array {
            $session = $this->get($app, $id);
            if (($session->selectedMethod !== null && $session->selectedMethod !== $method)
                || ($session->allowedMethods !== [] && !\in_array($method, $session->allowedMethods, true))) {
                throw PaymentCashierException::conflict($id);
            }

            $context = $this->context($session);
            if ($session->paymentOrderId !== null) {
                $order = $this->orders->getById($session->paymentOrderId);

                return [$order, null];
            }

            $route = $this->routes->resolveMethod($app, $method, $context, policyName: $session->routingPolicy);
            $remaining = max(1, (int) ceil(($session->expireAt->getTimestamp() - $this->clock->now()->getTimestamp()) / 60));
            $request = PaymentOrderRequest::fromArray(array_replace($session->payload, [
                'expire_minutes' => $remaining,
                'idempotency_key' => 'cashier:' . $session->id,
            ]));
            $order = $this->orders->create($app, $request);
            $this->sessions->update([['id' => $id, 'selectedMethod' => $method, 'paymentOrderId' => $order->id]], Context::createDefaultContext());

            return [$order, $route];
        });

        if (!$this->orders->isPayable($order)) {
            return $this->presenter->order($order);
        }

        $result = $this->starter->replay($order);
        if ($result === null) {
            if ($route === null) {
                $session = $this->get($app, $id);
                $route = $this->routes->resolveMethod($app, $method, $this->context($session), policyName: $session->routingPolicy);
            }
            $result = $this->starter->start($app, $order, $route);
        }

        return $this->presenter->order($this->orders->getById($order->id), $result);
    }

    private function config(PaymentAppEntity $app, string $profile): PaymentCashierConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('technicalName', $profile), new EqualsFilter('active', true));
        $criteria->setLimit(1);
        $config = $this->configs->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentCashierConfigEntity ? $config : throw PaymentCashierException::unavailable($profile);
    }

    private function findByTrade(PaymentAppEntity $app, string $trade): ?PaymentCashierSessionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('outTradeNo', $trade));
        $criteria->setLimit(1);
        $session = $this->sessions->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $session instanceof PaymentCashierSessionEntity ? $session : null;
    }

    /**
     * @param array<string, mixed> $business
     * @param array<string, mixed> $context
     */
    private function replay(PaymentCashierSessionEntity $session, PaymentCashierConfigEntity $config, array $business, array $context): PaymentCashierSessionEntity
    {
        if ($session->cashierConfigId !== $config->id || $this->canonical($session->payload) !== $this->canonical($business) || $this->canonical($session->paymentContext) !== $this->canonical($context)) {
            throw PaymentCashierException::conflict($session->id);
        }
        if ($session->expireAt <= $this->clock->now()) {
            throw PaymentCashierException::expired($session->id);
        }

        return $session;
    }

    /**
     * JSON object key order can change in storage; scalar types and list order remain significant.
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function canonical(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->canonical($value);
            }
        }
        if (!array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotContext(PaymentContext $context): array
    {
        return [
            'currency' => $context->currency,
            'scene' => ['name' => $context->scene->name, 'device' => $context->scene->device, 'environment' => $context->scene->environment],
            'interactions' => $context->supportedInteractions,
            'sdk_products' => $context->supportedSdkProducts,
            'runtime_params' => $context->runtimeParams,
            'account_runtime_params' => $context->accountRuntimeParams,
        ];
    }

    private function context(PaymentCashierSessionEntity $session): PaymentContext
    {
        $snapshot = $session->paymentContext;

        return new PaymentContext(
            currency: $snapshot['currency'],
            scene: new PaymentScene($snapshot['scene']['name'], $snapshot['scene']['device'], $snapshot['scene']['environment']),
            supportedInteractions: $snapshot['interactions'],
            supportedSdkProducts: $snapshot['sdk_products'],
            runtimeParams: $snapshot['runtime_params'],
            accountRuntimeParams: $snapshot['account_runtime_params'],
        );
    }
}
