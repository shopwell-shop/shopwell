<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Input;

use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitCollection;
use Shopwell\Core\System\User\UserCollection;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowEntityFields
{
    public const TYPES = ['user', 'users', 'department', 'media', 'mediaList', 'images'];

    /**
     * @param EntityRepository<UserCollection> $users
     * @param EntityRepository<OrganizationUnitCollection> $departments
     * @param EntityRepository<MediaCollection>|null $media
     */
    public function __construct(private EntityRepository $users, private EntityRepository $departments, private ?EntityRepository $media = null)
    {
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return string|list<string>
     */
    public function normalize(array $field, mixed $value, Context $context): string|array
    {
        $multiple = \in_array($field['type'], ['users', 'mediaList', 'images'], true);
        $ids = self::identifiers($multiple ? $value : [$value], 50);
        if (isset($field['candidateIds']) && array_diff($ids, $field['candidateIds']) !== []) {
            throw WorkflowExecutionException::invalidGraph('The selected value is outside the configured candidate range.');
        }

        $criteria = (new Criteria($ids))->addFilter(new EqualsFilter('active', true));
        if (\in_array($field['type'], ['media', 'mediaList', 'images'], true)) {
            if ($this->media === null || !$context->isAllowed('media:read')) {
                throw WorkflowExecutionException::forbidden('The current user cannot use media in this workflow.');
            }

            $mediaEntities = $this->media->search(new Criteria($ids), $context)->getEntities();
            if ($mediaEntities->count() !== \count($ids)) {
                throw WorkflowExecutionException::invalidGraph('A selected attachment is no longer available.');
            }
            foreach ($mediaEntities as $media) {
                \assert($media instanceof MediaEntity);
                if (!$media->hasFile() || ($field['type'] === 'images' && !str_starts_with((string) $media->getMimeType(), 'image/'))) {
                    throw WorkflowExecutionException::invalidGraph('Workflow attachments must be uploaded files of the configured type.');
                }
            }
            $available = $ids;
        } elseif ($field['type'] === 'department') {
            $criteria->addFilter(new EqualsFilter('organization.active', true));
            $available = $this->departments->searchIds($criteria, $context)->getIds();
        } else {
            $available = $this->users->searchIds($criteria, $context)->getIds();
        }
        if (array_diff($ids, $available) !== []) {
            throw WorkflowExecutionException::invalidGraph('A selected person or department is no longer available.');
        }

        return $multiple ? $ids : $ids[0];
    }

    /**
     * @return non-empty-list<string>
     */
    public static function identifiers(mixed $value, int $limit = 200): array
    {
        if (!\is_array($value) || !array_is_list($value) || $value === [] || \count($value) > $limit) {
            throw WorkflowExecutionException::invalidGraph('Select between one and ' . $limit . ' unique identifiers.');
        }

        $ids = [];
        foreach ($value as $id) {
            if (!\is_string($id) || !Uuid::isValid(strtolower($id)) || \in_array(strtolower($id), $ids, true)) {
                throw WorkflowExecutionException::invalidGraph('Selections require unique valid identifiers.');
            }
            $ids[] = strtolower($id);
        }

        return $ids;
    }
}
