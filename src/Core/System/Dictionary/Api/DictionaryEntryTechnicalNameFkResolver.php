<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopwell\Core\Framework\Api\Sync\FkReference;
use Shopwell\Core\Framework\Log\Package;

/**
 * Resolves technical names of dictionary entries to their ids. The lookup is
 * scoped to a dictionary type (default: salutation), because technical names
 * are only unique within one dictionary type.
 *
 * @internal
 */
#[Package('fundamentals@discovery')]
class DictionaryEntryTechnicalNameFkResolver extends AbstractFkResolver
{
    final public const DEFAULT_TYPE = 'salutation';

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getName(): string
    {
        return 'dictionary_entry.technical_name';
    }

    /**
     * @param array<FkReference> $map
     *
     * @return array<FkReference>
     */
    public function resolve(array $map): array
    {
        $keys = \array_map(static fn ($reference) => $reference->value, $map);

        $keys = \array_filter(\array_unique($keys));

        if ($keys === []) {
            return $map;
        }

        $hash = $this->connection->fetchAllKeyValue(
            'SELECT de.technical_name, LOWER(HEX(de.id))
             FROM dictionary_entry de
             INNER JOIN dictionary_type dt ON dt.id = de.dictionary_type_id
             WHERE dt.technical_name = :type AND de.technical_name IN (:keys)',
            ['type' => self::DEFAULT_TYPE, 'keys' => \array_values($keys)],
            ['keys' => ArrayParameterType::STRING]
        );

        foreach ($map as $reference) {
            if (isset($hash[$reference->value])) {
                $reference->resolved = $hash[$reference->value];
            }
        }

        return $map;
    }
}
