<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Ledger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The row a balance or a purchase is written on, locked until the caller's transaction ends.
 *
 * Both ledgers and both purchase settlements carried the same three steps: address the row by its key,
 * read it `FOR UPDATE`, and open it when it does not exist yet although a parallel transaction may win
 * that race. Getting any of the three wrong loses a booking instead of failing it, so they live here
 * once, in the one place that can be read for "how is a balance protected".
 *
 * Nothing here knows what the row means: the caller passes the table, the key and the row it wants to
 * insert. A row read through this class outside of `Connection::transactional()` is not protected -
 * the lock ends with the transaction that took it.
 */
#[Package('checkout')]
class RowLock
{
    /**
     * The uuid columns are read as bytes by the database. Answering them as hex keeps the callers free
     * of the conversion, and every ledger table names its references the same way.
     */
    private const UUID_COLUMN_SUFFIX = '_id';

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Locks the row addressed by the given columns and answers it, or null when there is none.
     *
     * @param array<string, string> $key column => hex uuid
     *
     * @return array<string, mixed>|null
     */
    public function lock(string $table, array $key): ?array
    {
        $conditions = [];
        $parameters = [];
        $types = [];

        foreach ($key as $column => $value) {
            $conditions[] = \sprintf('`%s` = :%s', $column, $column);
            $parameters[$column] = Uuid::fromHexToBytes($value);
            $types[$column] = ParameterType::BINARY;
        }

        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT * FROM `%s` WHERE %s FOR UPDATE', $table, implode(' AND ', $conditions)),
            $parameters,
            $types
        );

        if ($row === false) {
            return null;
        }

        return $this->toHex($row);
    }

    /**
     * Writes the row when nobody holds it yet.
     *
     * Losing that race is the expected outcome and not an error: the winner's row is the one the caller
     * locks afterwards, while a duplicate key exception would abort the surrounding transaction and with
     * it the booking that was about to happen.
     *
     * @template TEntityCollection of EntityCollection
     *
     * @param EntityRepository<TEntityCollection> $repository
     * @param array<string, mixed> $row
     */
    public function createUnlessExists(EntityRepository $repository, array $row, Context $context): void
    {
        try {
            $repository->create([$row], $context);
        } catch (UniqueConstraintViolationException) {
            // a parallel transaction opened the very same row first, its row is the one to book onto
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function toHex(array $row): array
    {
        foreach ($row as $column => $value) {
            if (\is_string($value) && $this->isUuidColumn($column)) {
                $row[$column] = Uuid::fromBytesToHex($value);
            }
        }

        return $row;
    }

    private function isUuidColumn(string $column): bool
    {
        return $column === 'id' || str_ends_with($column, self::UUID_COLUMN_SUFFIX);
    }
}
