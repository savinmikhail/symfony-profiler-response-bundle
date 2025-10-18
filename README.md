![dist-size status](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsavinmikhail%2Fdist-size-optimizer%2Fmain%2Fdist-size-status.json)

## Symfony Profiler Response Bundle

Dev-only Symfony bundle that adds a "Response Body" panel to the Web Profiler, showing JSON/text response payloads with size limits and sensible guards.

Features
- Captures response body for textual and JSON-like MIME types
- Skips streamed/binary responses to avoid breaking downloads
- Truncates large payloads (default 256 KB) to protect the toolbar
- Pretty-prints JSON when possible

### Installation
1) `composer req --dev savinmikhail/symfony-profiler-response-bundle`
2) Register the bundle in your app (dev only recommended):

```
// config/bundles.php
return [
    // ...
    SavinMikhail\\ResponseProfilerBundle\\ResponseProfilerBundle::class => ['dev' => true],
];
```

3) Optional config (dev): `config/packages/dev/response_profiler.yaml`

```
response_profiler:
  enabled: true
  max_length: 262144           # bytes (256 KB)
  allowed_mime_types:
    - application/json
    - application/ld+json
    - application/problem+json
    - application/vnd.api+json
    - text/plain
    - text/json
```

### Usage
- In the Web Profiler, open any request and look for the new "Response Body" tab.
- The toolbar badge shows MIME and size; the panel shows headers and the (pretty-printed) body, truncated if oversized.

Appearance

![img.png](img.png)

Notes
- This bundle is designed for development. Do not enable in production.
- Streamed and binary responses are ignored.
- Pretty-printing of very large JSON may be skipped when payloads are huge.
