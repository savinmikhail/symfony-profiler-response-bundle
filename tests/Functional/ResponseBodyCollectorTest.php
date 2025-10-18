<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use SavinMikhail\ResponseProfilerBundle\Collector\ResponseBodyCollector;

final class ResponseBodyCollectorTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        if ($this->kernel) {
            $this->kernel->shutdown();
        }
        $this->kernel = null;
    }

    private function requestAndGetCollector(string $path): ResponseBodyCollector
    {
        $request = Request::create($path, 'GET');
        $response = $this->kernel->handle($request);

        /** @var Profiler $profiler */
        $profiler = $this->kernel->getContainer()->get('profiler');
        $token = (string) $response->headers->get('X-Debug-Token');
        $profile = $profiler->loadProfile($token);
        self::assertNotNull($profile, 'Profile should be available');
        /** @var ResponseBodyCollector $collector */
        $collector = $profile->getCollector('response_body');
        return $collector;
    }

    public function testCapturesJsonBody(): void
    {
        $collector = $this->requestAndGetCollector('/json');
        self::assertTrue($collector->isCaptured());
        self::assertTrue($collector->isJson());
        self::assertStringContainsString('hello', $collector->getContent());
        self::assertStringContainsString('world', $collector->getContent());
    }

    public function testCapturesTextBody(): void
    {
        $collector = $this->requestAndGetCollector('/text');
        self::assertTrue($collector->isCaptured());
        self::assertFalse($collector->isJson());
        self::assertSame('plain text body', $collector->getContent());
    }

    public function testIgnoresNonTextualPdf(): void
    {
        $collector = $this->requestAndGetCollector('/pdf');
        self::assertFalse($collector->isCaptured());
        self::assertSame('non_textual', $collector->getReason());
    }

    public function testIgnoresStreamedResponse(): void
    {
        $collector = $this->requestAndGetCollector('/stream');
        self::assertFalse($collector->isCaptured());
        self::assertSame('streamed_or_binary', $collector->getReason());
    }

    public function testIgnoresBinaryFileResponse(): void
    {
        $collector = $this->requestAndGetCollector('/binary');
        self::assertFalse($collector->isCaptured());
        self::assertSame('streamed_or_binary', $collector->getReason());
    }

    public function testTruncatesLargeBodies(): void
    {
        $collector = $this->requestAndGetCollector('/bigjson');
        self::assertTrue($collector->isCaptured());
        self::assertTrue($collector->isTruncated());
        self::assertGreaterThan($collector->getDisplaySize(), $collector->getSize());
        self::assertSame(1024, $collector->getMaxLength());
    }
}

