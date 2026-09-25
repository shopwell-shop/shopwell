<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Twig\Extension;

use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class MediaExtension extends AbstractExtension
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(private readonly EntityRepository $mediaRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('searchMedia', $this->searchMedia(...)),
        ];
    }

    /**
     * @param array<string> $ids
     */
    public function searchMedia(array $ids, Context $context): MediaCollection
    {
        if ($ids === []) {
            return new MediaCollection();
        }

        $criteria = new Criteria($ids);

        return $this->mediaRepository->search($criteria, $context)->getEntities();
    }
}
