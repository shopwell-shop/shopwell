<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Renderer;

use Shopwell\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: RenderResult::class,
)]
final class RendererResult extends Struct
{
    /**
     * @var array<string, RenderedDocument>
     */
    protected array $success = [];

    /**
     * @var array<string, \Throwable>
     */
    protected array $errors = [];

    public function addSuccess(string $orderId, RenderedDocument $renderedDocument): void
    {
        $this->success[$orderId] = $renderedDocument;
    }

    public function addError(string $orderId, \Throwable $exception): void
    {
        $this->errors[$orderId] = $exception;
    }

    /**
     * @return array<string, RenderedDocument>
     */
    public function getSuccess(): array
    {
        return $this->success;
    }

    /**
     * @return array<string, \Throwable>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getOrderSuccess(string $orderId): ?RenderedDocument
    {
        return $this->success[$orderId] ?? null;
    }

    public function getOrderError(string $orderId): ?\Throwable
    {
        return $this->errors[$orderId] ?? null;
    }
}
