<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Demodata\Generator;

use Shopwell\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Demodata\DemodataContext;
use Shopwell\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopwell\Core\Framework\Demodata\DemodataService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class MailHeaderFooterGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly MailHeaderFooterDefinition $mailHeaderFooterDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return MailHeaderFooterDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->createMailHeaderFooter(
            $context,
            $numberOfItems
        );
    }

    private function createMailHeaderFooter(DemodataContext $context, int $numberOfItems): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $mailHeaderFooter = $this->prepareHeaderFooterData($context);

            $payload[] = $mailHeaderFooter;

            if (\count($payload) >= 50) {
                $context->getConsole()->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->write($payload, $context);
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareHeaderFooterData(DemodataContext $context): array
    {
        $faker = $context->getFaker();

        return [
            'id' => Uuid::randomHex(),
            'name' => $faker->text(50),
            'description' => $faker->text(),
            'isSystemDefault' => false,
            'headerHtml' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'headerPlain' => $faker->text(),
            'footerHtml' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'footerPlain' => $faker->text(),
            'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
        ];
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function write(array $payload, DemodataContext $context): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $this->writer->upsert($this->mailHeaderFooterDefinition, $payload, $writeContext);
    }

    /**
     * @param list<string> $tags
     */
    private function generateRandomHTML(int $count, array $tags, DemodataContext $context): string
    {
        $output = '';
        for ($i = 0; $i < $count; ++$i) {
            $tag = Random::getRandomArrayElement($tags);
            $text = $context->getFaker()->words(random_int(1, 10), true);
            if (\is_array($text)) {
                $text = implode(' ', $text);
            }
            $output .= \sprintf('<%1$s>%2$s</%1$s>', $tag, $text);
            $output .= '<br/>';
        }

        return $output;
    }
}
