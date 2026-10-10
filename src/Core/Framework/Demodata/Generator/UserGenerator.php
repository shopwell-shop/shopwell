<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Demodata\Generator;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Demodata\DemodataContext;
use Shopwell\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopwell\Core\Framework\Demodata\DemodataService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class UserGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly UserDefinition $userDefinition,
        private readonly EntityRepository $languageRepository
    ) {
    }

    public function getDefinition(): string
    {
        return UserDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::randomHex();
            $name = $context->getFaker()->format('name');
            $title = $this->getRandomTitle();

            $user = [
                'id' => $id,
                'title' => $title,
                'name' => $name,
                'username' => $context->getFaker()->format('userName'),
                'email' => $id . $context->getFaker()->format('safeEmail'),
                'password' => 'shopwell',
                'localeId' => $this->getLocaleId($context->getContext()),
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];

            $payload[] = $user;

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->userDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->writer->upsert($this->userDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        $context->getConsole()->progressFinish();
    }

    private function getRandomTitle(): string
    {
        $titles = ['', 'Dr.', 'Dr. med.', 'Prof.', 'Prof. Dr.'];

        return $titles[array_rand($titles)];
    }

    private function getLocaleId(Context $context): string
    {
        $first = $this->languageRepository->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), $context)->getEntities()->first();
        \assert($first !== null);

        return $first->getLocaleId();
    }
}
