<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;
use SavinMikhail\ResponseProfilerBundle\Collector\ResponseBodyCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final class ResponseBodyCollectorTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel(environment: 'test', debug: true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        if ($this->kernel instanceof TestKernel) {
            $this->kernel->shutdown();
        }
        $this->kernel = null;
    }

    private function requestAndGetCollector(string $path): ResponseBodyCollector
    {
        $request = Request::create(uri: $path);
        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        /** @var Profiler $profiler */
        $profiler = $this->kernel->getContainer()->get('profiler');
        $token = (string) $response->headers->get(key: 'X-Debug-Token');
        $profile = $token !== '' ? $profiler->loadProfile(token: $token) : null;
        if ($profile === null) {
            // Fallback: collect directly if storage/token not set
            $profile = $profiler->collect($request, $response);
        }
        /** @var ResponseBodyCollector $collector */
        $collector = $profile->getCollector('response_body');

        return $collector;
    }

    public function testCapturesJsonBody(): void
    {
        $collector = $this->requestAndGetCollector(path: '/json');
        self::assertTrue($collector->isCaptured());
        self::assertTrue($collector->isJson());
        self::assertStringContainsString('hello', $collector->getContent());
        self::assertStringContainsString('world', $collector->getContent());
    }

    public function testCapturesTextBody(): void
    {
        $collector = $this->requestAndGetCollector(path: '/text');
        self::assertTrue($collector->isCaptured());
        self::assertFalse($collector->isJson());
        self::assertSame('plain text body', $collector->getContent());
    }

    public function testIgnoresNonTextualPdf(): void
    {
        $collector = $this->requestAndGetCollector(path: '/pdf');
        self::assertFalse($collector->isCaptured());
        self::assertSame('non_textual', $collector->getReason());
    }

    public function testIgnoresStreamedResponse(): void
    {
        $collector = $this->requestAndGetCollector(path: '/stream');
        self::assertFalse($collector->isCaptured());
        self::assertSame('streamed_or_binary', $collector->getReason());
    }

    public function testIgnoresBinaryFileResponse(): void
    {
        $collector = $this->requestAndGetCollector(path: '/binary');
        self::assertFalse($collector->isCaptured());
        self::assertSame('streamed_or_binary', $collector->getReason());
    }

    public function testTruncatesLargeBodies(): void
    {
        $collector = $this->requestAndGetCollector(path: '/bigjson');
        self::assertTrue($collector->isCaptured());
        self::assertTrue($collector->isTruncated());
        self::assertGreaterThan($collector->getDisplaySize(), $collector->getSize());
        self::assertSame(1_024, $collector->getMaxLength());
    }
}
