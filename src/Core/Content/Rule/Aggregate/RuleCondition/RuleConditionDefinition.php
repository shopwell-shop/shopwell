<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Rule\Aggregate\RuleCondition;

use Shopwell\Core\Content\Rule\RuleDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class RuleConditionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'rule_condition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return RuleConditionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return RuleConditionCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return RuleDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of rule condition.'),
            (new StringField('type', 'type'))->addFlags(new Required())->setDescription('Different rule types.'),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required())->setDescription('Unique identity of rule.'),
            (new FkField('script_id', 'scriptId', AppScriptConditionDefinition::class))->setDescription('Unique identity of script.'),
            new ParentFkField(self::class),
            (new JsonField('value', 'value'))->setDescription('Value of the RuleCondition.'),
            (new IntField('position', 'position'))->setDescription('The order of the tabs of your defined rule setting configurations in the Administration by entering numerical values like 1,2,3, etc.'),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id'),
            new ManyToOneAssociationField('appScriptCondition', 'script_id', AppScriptConditionDefinition::class, 'id'),
            (new ParentAssociationField(self::class, 'id'))->setDescription('Unique identity of rule condition.'),
            new ChildrenAssociationField(self::class),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
