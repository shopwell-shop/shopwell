<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @no-indexer-required: false positive. 'payment_method.indexer' is per row, not cross-row.
 */
#[Package('checkout')]
class Migration1743256470RemoveDebitPayment extends MigrationStep
{
    public const METHOD_HANDLER = 'Shopwell\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\DebitPayment';

    public function getCreationTimestamp(): int
    {
        return 1743256470;
    }

    public function update(Connection $connection): void
    {
        $ids = $connection->fetchFirstColumn(
            'SELECT id FROM payment_method WHERE handler_identifier = :handler',
            ['handler' => self::METHOD_HANDLER]
        );

        foreach ($ids as $id) {
            try {
                $connection->delete('payment_method', ['id' => $id]);
            } catch (Exception) {
                $connection->update(
                    'payment_method',
                    [
                        'handler_identifier' => DefaultPayment::class,
                        'active' => 0,
                    ],
                    ['id' => $id],
                );
            }
        }
    }
}
