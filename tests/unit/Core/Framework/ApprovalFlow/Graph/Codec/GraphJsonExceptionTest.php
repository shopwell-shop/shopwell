<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphViolation;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\ViolationCategory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphJsonException::class)]
class GraphJsonExceptionTest extends TestCase
{
    public function testRetainsStructuredViolationAndOriginalCauseWithTheGraphErrorContract(): void
    {
        $violation = new GraphViolation(ViolationCategory::Structure, 'invalid_json', '/config', 'Malformed JSON.');
        $previous = new \JsonException('Native parsing failure.');

        $exception = GraphJsonException::forViolation($violation, $previous);

        static::assertSame($violation, $exception->violation);
        static::assertSame($previous, $exception->getPrevious());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('APPROVAL_FLOW__INVALID_GRAPH', $exception->getErrorCode());
        static::assertSame(['path' => '/config', 'reason' => 'Malformed JSON.'], $exception->getParameters());
    }
}
