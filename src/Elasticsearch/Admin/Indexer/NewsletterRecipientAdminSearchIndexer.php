<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipientTag\NewsletterRecipientTagDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopwell\Elasticsearch\Framework\ElasticsearchFieldBuilder;

#[Package('inventory')]
final class NewsletterRecipientAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<NewsletterRecipientCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return NewsletterRecipientDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'newsletter-recipient-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $newsletterRecipientIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'email',
            'name',
            'status',
            'city',
            'zipCode',
            'street',
            'salesChannelId',
            'languageId',
        ]);

        $tags = $event->getPrimaryKeysWithPropertyChange(NewsletterRecipientTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach ($tags as $pks) {
            if (isset($pks['newsletterRecipientId'])) {
                $newsletterRecipientIds[] = $pks['newsletterRecipientId'];
            }
        }

        return array_values(array_unique(array_filter($newsletterRecipientIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $override = [
            'email' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'name' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'status' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'city' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'zipCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'street' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'salesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'languageId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'updatedAt' => ElasticsearchFieldBuilder::datetime(),
            'tags' => ElasticsearchFieldBuilder::nested(),
        ];

        $mapping['properties'] ??= [];
        $mapping['properties'] = array_merge($mapping['properties'], $override);

        return $mapping;
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    /**
     * @return array<string, array{
     *     id: string,
     *     text: string,
     *     completion: list<string>,
     *     email?: mixed,
     *     name?: mixed,
     *     status?: mixed,
     *     city?: mixed,
     *     zipCode?: mixed,
     *     street?: mixed,
     *     salesChannelId?: mixed,
     *     languageId?: mixed,
     *     tags?: list<array{id: string, _count: int}>,
     *     createdAt?: string|null,
     *     updatedAt?: string|null
     * }>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(newsletter_recipient.id)) as id,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   GROUP_CONCAT(LOWER(HEX(tag.id)) SEPARATOR " ") as tagIds,
                   newsletter_recipient.email,
                   newsletter_recipient.name,
                   newsletter_recipient.status,
                   newsletter_recipient.city,
                   newsletter_recipient.zip_code AS zipCode,
                   newsletter_recipient.street,
                   LOWER(HEX(newsletter_recipient.sales_channel_id)) AS salesChannelId,
                   LOWER(HEX(newsletter_recipient.language_id)) AS languageId,
                   newsletter_recipient.created_at as createdAt,
                   newsletter_recipient.updated_at as updatedAt
            FROM newsletter_recipient
                LEFT JOIN newsletter_recipient_tag
                    ON newsletter_recipient.id = newsletter_recipient_tag.newsletter_recipient_id
                LEFT JOIN tag
                    ON newsletter_recipient_tag.tag_id = tag.id
            WHERE newsletter_recipient.id IN (:ids)
            GROUP BY newsletter_recipient.id
SQL,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([
                $row['email'] ?? '',
                $row['name'] ?? '',
                $row['city'] ?? '',
                $row['zipCode'] ?? '',
                $row['street'] ?? '',
                $row['tags'] ?? '',
                $id,
            ]));

            $completion = $this->buildCompletion([
                \is_string($row['email'] ?? null) ? $row['email'] : null,
                \is_string($row['name'] ?? null) ? $row['name'] : null,
            ]);

            if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                $mapped[$id] = [
                    'id' => $id,
                    'text' => \strtolower($text),
                    'completion' => $completion,
                ];

                continue;
            }

            $mapped[$id] = [
                'id' => $id,
                'text' => \strtolower($text),
                'completion' => $completion,
                'email' => $row['email'] ?? null,
                'name' => $row['name'] ?? null,
                'status' => $row['status'] ?? null,
                'city' => $row['city'] ?? null,
                'zipCode' => $row['zipCode'] ?? null,
                'street' => $row['street'] ?? null,
                'salesChannelId' => $row['salesChannelId'] ?? null,
                'languageId' => $row['languageId'] ?? null,
                'tags' => $this->parseTagIds($row),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
                'updatedAt' => $this->formatDateTime($row, 'updatedAt'),
            ];
        }

        return $mapped;
    }
}
