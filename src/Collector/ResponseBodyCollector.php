<?php

declare(strict_types=1);

namespace SavinMikhail\ResponseProfilerBundle\Collector;

use JsonException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

use function in_array;
use function is_string;
use function strlen;

/**
 * @no-named-arguments
 */
final class ResponseBodyCollector extends DataCollector
{
    /**
     * @param string[] $allowedMimeTypes
     */
    public function __construct(
        private readonly bool $enabled, 
        private readonly int $maxLength = 262_144, 
        private readonly array $allowedMimeTypes = []
    ) {
        $this->reset();
    }

    /**
     * @throws JsonException
     */
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->isStreamedOrBinary(response: $response)) {
            $this->markNotCaptured(reason: 'streamed_or_binary', contentTypeHeader: $this->getContentTypeHeader(response: $response));
            return;
        }

        $contentTypeHeader = $this->getContentTypeHeader(response: $response);
        $mime = $this->extractMime(contentTypeHeader: $contentTypeHeader);

        [$isJson, $isTextual] = $this->determineNature(response: $response, mime: $mime);

        if (!$isTextual) {
            $this->markNotCaptured(reason: 'non_textual', contentTypeHeader: $contentTypeHeader);
            return;
        }

        $raw = $this->getContentSafely(response: $response);
        if ($raw === null) {
            $this->markNotCaptured(reason: 'unavailable_content', contentTypeHeader: $contentTypeHeader);
            return;
        }

        $originalSize = strlen(string: $raw);

        [$rawDisplay, $prettyDisplay] = $this->prepareDisplays(raw: $raw, isJson: $isJson);

        [$rawDisplay, $prettyDisplay, $rawTruncated, $prettyTruncated] = $this->applyTruncation(
            rawDisplay: $rawDisplay,
            prettyDisplay: $prettyDisplay,
            limit: $this->maxLength
        );

        $this->setCapturedData(
            contentTypeHeader: $contentTypeHeader,
            mime: $mime,
            isJson: $isJson,
            originalSize: $originalSize,
            rawDisplay: $rawDisplay,
            prettyDisplay: $prettyDisplay,
            rawTruncated: $rawTruncated,
            prettyTruncated: $prettyTruncated
        );
    }

    public function getName(): string
    {
        return 'response_body';
    }

    public function reset(): void
    {
        $this->data = [
            'captured' => false,
            'reason' => null,
            'content' => '',
            'raw' => '',
            'pretty' => null,
            'truncated' => false,
            'truncatedRaw' => false,
            'truncatedPretty' => false,
            'size' => 0,
            'displaySize' => 0,
            'maxLength' => $this->maxLength,
            'contentType' => '',
            'mime' => '',
            'json' => false,
        ];
    }

    public function isCaptured(): bool
    {
        return (bool) ($this->data['captured'] ?? false);
    }

    public function getReason(): ?string
    {
        return $this->data['reason'] ?? null;
    }

    public function getContent(): string
    {
        return (string) ($this->data['content'] ?? '');
    }

    public function getRaw(): string
    {
        return (string) ($this->data['raw'] ?? '');
    }

    public function getPretty(): ?string
    {
        return isset($this->data['pretty']) && is_string(value: $this->data['pretty']) ? $this->data['pretty'] : null;
    }

    public function hasPretty(): bool
    {
        return is_string(value: $this->getPretty());
    }

    public function isTruncated(): bool
    {
        return (bool) ($this->data['truncated'] ?? false);
    }

    public function isTruncatedRaw(): bool
    {
        return (bool) ($this->data['truncatedRaw'] ?? false);
    }

    public function isTruncatedPretty(): bool
    {
        return (bool) ($this->data['truncatedPretty'] ?? false);
    }

    public function getSize(): int
    {
        return (int) ($this->data['size'] ?? 0);
    }

    public function getDisplaySize(): int
    {
        return (int) ($this->data['displaySize'] ?? 0);
    }

    public function getMaxLength(): int
    {
        return (int) ($this->data['maxLength'] ?? 0);
    }

    public function getContentType(): string
    {
        return (string) ($this->data['contentType'] ?? '');
    }

    public function getMime(): string
    {
        return (string) ($this->data['mime'] ?? '');
    }

    public function isJson(): bool
    {
        return (bool) ($this->data['json'] ?? false);
    }

    private function isStreamedOrBinary(Response $response): bool
    {
        return $response instanceof StreamedResponse || $response instanceof BinaryFileResponse;
    }

    private function getContentTypeHeader(Response $response): string
    {
        return (string) $response->headers->get(key: 'Content-Type', default: '');
    }

    private function extractMime(string $contentTypeHeader): string
    {
        return strtolower(string: trim(string: explode(separator: ';', string: $contentTypeHeader)[0] ?? ''));
    }

    /**
     * @return array{0: bool, 1: bool} [isJson, isTextual]
     */
    private function determineNature(Response $response, string $mime): array
    {
        $isJson = $response instanceof JsonResponse || str_contains(haystack: $mime, needle: 'json');
        $isTextual = $isJson || ($mime !== '' && (str_starts_with(haystack: $mime, needle: 'text/') || in_array(needle: $mime, haystack: $this->allowedMimeTypes, strict: true)));

        return [$isJson, $isTextual];
    }

    private function getContentSafely(Response $response): ?string
    {
        try {
            return (string) $response->getContent();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     * @throws JsonException
     */
    private function prepareDisplays(string $raw, bool $isJson): array
    {
        $rawDisplay = $raw;
        $prettyDisplay = null;

        if ($isJson) {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pretty = json_encode($decoded, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $prettyDisplay = $pretty;
            }
        }

        return [$rawDisplay, $prettyDisplay];
    }

    /**
     * @return array{0: string, 1: ?string, 2: bool, 3: bool}
     */
    private function applyTruncation(string $rawDisplay, ?string $prettyDisplay, int $limit): array
    {
        $rawTruncated = false;
        $prettyTruncated = false;

        if ($limit > 0) {
            if (strlen(string: $rawDisplay) > $limit) {
                $rawDisplay = substr(string: $rawDisplay, offset: 0, length: $limit);
                $rawTruncated = true;
            }

            if (is_string(value: $prettyDisplay) && strlen(string: $prettyDisplay) > $limit) {
                $prettyDisplay = substr(string: $prettyDisplay, offset: 0, length: $limit);
                $prettyTruncated = true;
            }
        }

        return [$rawDisplay, $prettyDisplay, $rawTruncated, $prettyTruncated];
    }

    private function setCapturedData(
        string $contentTypeHeader,
        string $mime,
        bool $isJson,
        int $originalSize,
        string $rawDisplay,
        ?string $prettyDisplay,
        bool $rawTruncated,
        bool $prettyTruncated
    ): void {
        $display = $prettyDisplay ?? $rawDisplay;

        $this->data = [
            'captured' => true,
            'content' => $display,
            'raw' => $rawDisplay,
            'pretty' => $prettyDisplay,
            'truncated' => $rawTruncated || $prettyTruncated,
            'truncatedRaw' => $rawTruncated,
            'truncatedPretty' => $prettyTruncated,
            'size' => $originalSize,
            'displaySize' => strlen(string: $display),
            'maxLength' => $this->maxLength,
            'contentType' => $contentTypeHeader,
            'mime' => $mime,
            'json' => $isJson,
        ];
    }

    private function markNotCaptured(string $reason, string $contentTypeHeader): void
    {
        $this->data['captured'] = false;
        $this->data['reason'] = $reason;
        $this->data['contentType'] = $contentTypeHeader;
    }
}
