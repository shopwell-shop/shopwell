<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Log;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class LogEntryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'log_entry';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LogEntryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LogEntryCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of log entry.'),

            (new LongTextField('message', 'message'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Indicates text or content of a log entry.'),
            (new IntField('level', 'level'))->setDescription('It indicates the level or severity of the log entry. For example: BUG, ERROR, etc.'),
            new StringField('channel', 'channel'),
            (new JsonField('context', 'context'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Information associated with a log entry.'),
            (new JsonField('extra', 'extra'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RANKING))->setDescription('Additional information associated with a log entry.'),
        ]);
    }
}
