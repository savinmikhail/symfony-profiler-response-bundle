<?php

declare(strict_types=1);

namespace SavinMikhail\ResponseProfilerBundle\Collector;

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

final class ResponseBodyCollector extends DataCollector
{
    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(private readonly bool $enabled, private readonly int $maxLength = 262_144, private readonly array $allowedMimeTypes = [])
    {
        $this->reset();
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            $this->data['captured'] = false;
            $this->data['reason'] = 'streamed_or_binary';
            $this->data['contentType'] = $response->headers->get(key: 'Content-Type', default: '');

            return;
        }

        $contentTypeHeader = (string) $response->headers->get(key: 'Content-Type', default: '');
        $mime = strtolower(string: trim(string: explode(separator: ';', string: $contentTypeHeader)[0] ?? ''));

        $isJson = $response instanceof JsonResponse || str_contains(haystack: $mime, needle: 'json');
        $isTextual = $isJson || ($mime !== '' && (str_starts_with(haystack: $mime, needle: 'text/') || in_array(needle: $mime, haystack: $this->allowedMimeTypes, strict: true)));

        if (!$isTextual) {
            $this->data['captured'] = false;
            $this->data['reason'] = 'non_textual';
            $this->data['contentType'] = $contentTypeHeader;

            return;
        }

        try {
            $raw = (string) $response->getContent();
        } catch (Throwable) {
            $this->data['captured'] = false;
            $this->data['reason'] = 'unavailable_content';
            $this->data['contentType'] = $contentTypeHeader;

            return;
        }

        $originalSize = strlen(string: $raw);

        $rawDisplay = $raw;
        $prettyDisplay = null;
        if ($isJson) {
            $decoded = json_decode(json: $raw, associative: true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pretty = json_encode(value: $decoded, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (is_string(value: $pretty)) {
                    $prettyDisplay = $pretty;
                }
            }
        }

        $rawTruncated = false;
        $prettyTruncated = false;
        if ($this->maxLength > 0) {
            if (strlen(string: $rawDisplay) > $this->maxLength) {
                $rawDisplay = substr(string: $rawDisplay, offset: 0, length: $this->maxLength);
                $rawTruncated = true;
            }
            if (is_string(value: $prettyDisplay) && strlen(string: $prettyDisplay) > $this->maxLength) {
                $prettyDisplay = substr(string: $prettyDisplay, offset: 0, length: $this->maxLength);
                $prettyTruncated = true;
            }
        }

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
            'maxLength' => $this->maxLength ?? 262_144,
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
}
