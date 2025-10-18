<?php

declare(strict_types=1);

namespace Tests\Functional\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TestController
{
    public static function json(): Response
    {
        return new JsonResponse(['hello' => 'world', 'answer' => 42]);
    }

    public static function text(): Response
    {
        return new Response('plain text body', 200, ['Content-Type' => 'text/plain']);
    }

    public static function pdf(): Response
    {
        return new Response('%PDF-1.4 binary data', 200, ['Content-Type' => 'application/pdf']);
    }

    public static function binary(): Response
    {
        $file = sys_get_temp_dir() . '/rf_binary_test_' . uniqid('', true) . '.bin';
        file_put_contents($file, random_bytes(16));
        // Set Content-Type to avoid mime guessing (symfony/mime not required in tests)
        return new BinaryFileResponse($file, 200, ['Content-Type' => 'application/octet-stream']);
    }

    public static function stream(): Response
    {
        return new StreamedResponse(static function (): void {
            echo 'streamed';
        });
    }

    public static function bigjson(): Response
    {
        $payload = ['chunk' => str_repeat('A', 3000)];
        return new JsonResponse($payload);
    }
}
