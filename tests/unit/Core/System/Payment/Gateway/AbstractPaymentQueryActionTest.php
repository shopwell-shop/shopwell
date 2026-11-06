<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentQueryAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractPaymentQueryAction::class)]
class AbstractPaymentQueryActionTest extends TestCase
{
    public function testRecordsTheRequestAnswerAndTypedResult(): void
    {
        $result = new QueryResult(QueryResult::STATUS_PAID, raw: ['trade_state' => 'SUCCESS']);
        $action = $this->action(static fn (): QueryResult => $result);
        $model = new PaymentGatewayModel(['payment_no' => 'PG-1']);

        $action->execute(new PaymentQuery($model));

        static::assertSame(['out_trade_no' => 'PG-1'], $model->request());
        static::assertSame(['trade_state' => 'SUCCESS'], $model->response());
        static::assertSame($result, $model->result(QueryResult::class, 'test_channel'));
    }

    public function testRecordsAnErrorAndKeepsTheCauseOnTheGatewayError(): void
    {
        $cause = new \RuntimeException('timeout');
        $action = $this->action(static fn (): QueryResult => throw $cause);
        $model = new PaymentGatewayModel(['payment_no' => 'PG-1']);

        try {
            $action->execute(new PaymentQuery($model));
            static::fail('A failed channel query has to throw.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
            static::assertSame($cause, $exception->getPrevious());
        }

        static::assertSame(['error' => 'timeout'], $model->response());
    }

    public function testSupportsOnlyThePaymentQueryOfTheGatewayModel(): void
    {
        $action = $this->action(static fn (): QueryResult => new QueryResult(QueryResult::STATUS_PENDING));

        static::assertTrue($action->supports(new PaymentQuery(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new \stdClass()));
    }

    private function action(\Closure $query): AbstractPaymentQueryAction
    {
        $api = new class extends AbstractPaymentApi {
            public function __construct()
            {
            }

            public function channel(): string
            {
                return 'test_channel';
            }
        };

        $action = new class($query) extends AbstractPaymentQueryAction {
            protected string $apiClass = AbstractPaymentApi::class;

            public function __construct(private readonly \Closure $query)
            {
            }

            protected function requestPayload(PaymentGatewayModel $model): array
            {
                return ['out_trade_no' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO)];
            }

            protected function query(PaymentGatewayModel $model): QueryResult
            {
                return ($this->query)($model);
            }
        };
        $action->setApi($api);

        return $action;
    }
}
