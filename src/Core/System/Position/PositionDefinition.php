<?php declare(strict_types=1);

namespace Shopwell\Core\System\Position;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Position\Aggregate\PositionTranslation\PositionTranslationDefinition;
use Shopwell\Core\System\User\Aggregate\UserPosition\UserPositionDefinition;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class PositionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'position';

    final public const DEFAULT_POSITION = 0;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PositionCollection::class;
    }

    public function getEntityClass(): string
    {
        return PositionEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'position' => self::DEFAULT_POSITION,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the position.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Name of the position.'),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('Numerical value that indicates the order in which the defined positions must be displayed.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the position is available for selection.'),
            new CreatedAtField(),
            new UpdatedAtField(),

            (new TranslationsAssociationField(PositionTranslationDefinition::class, 'position_id'))->addFlags(new ApiAware(), new Required()),
            (new OneToManyAssociationField('userPositions', UserPositionDefinition::class, 'position_id', 'id'))->addFlags(new CascadeDelete()),
            new ManyToManyAssociationField('users', UserDefinition::class, UserPositionDefinition::class, 'position_id', 'user_id'),
        ]);
    }
}
