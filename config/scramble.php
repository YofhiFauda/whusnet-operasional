<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     *
     * 'api_path' => [
     *     'include' => 'api',
     *     'exclude' => ['api/internal'],
     * ],
     *
     * Without *, patterns match path segments (api matches api and api/users, not apiary).
     * With *, Str::is is used (e.g. api/v*).
     *
     * One static include → default server is /{include} and paths are stripped (/users).
     * Multiple includes or wildcards → server defaults to / and paths stay full (/api/users).
     * Override with `servers`, or use Scramble::registerApi() for separate bases.
     */
    // Sengaja 'api/v1', BUKAN 'api' polos — repo ini punya endpoint AJAX lama
    // ber-prefix `api/...` di routes/web.php (mis. api/wilayah/cities,
    // api/districts/{id}/villages, cascading dropdown wilayah) yang otentikasi
    // sesi/cookie, BUKAN bearer token, dan bukan API eksternal yang dimaksud
    // di sini. Matcher 'api' polos ikut menyeret semuanya ke dokumen yang
    // sama seolah satu kontrak — membingungkan dan salah: dua model
    // keamanan beda dicampur satu dokumen. 'api/v1' cuma cocok dua endpoint
    // baru di routes/api.php (docs/api/api-pop-distribusi/).
    'api_path' => 'api/v1',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    /*
     * Cache configuration for the generated OpenAPI document.
     *
     * Use `scramble:cache` to warm the cache and `scramble:clear` to invalidate it.
     */
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION', '0.0.1'),

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => <<<'MARKDOWN'
            # Whusnet Operational API Documentation

            Selamat datang di **Dokumentasi Resmi Whusnet Operational API**. 

            Dokumen ini merupakan panduan teknis standar (*Official Technical Reference*) yang diterbitkan untuk mengatur integrasi antarsistem (*inbound API integration*) antara platform mitra/aplikasi eksternal dengan ekosistem infrastruktur **Whusnet**.

            ---

            ### **Ringkasan Sistem (System Overview)**

            Whusnet Operational API bertindak sebagai *gateway* komunikasi data *inbound* untuk memfasilitasi pertukaran data operasional secara *real-time*, transparan, dan terstruktur. API ini dirancang khusus untuk menangani alur kerja integrasi *PoP (Point of Presence)*, pemetaan topologi, hingga konfirmasi alokasi jaringan secara otomatis.

            ---

            ### **Modul Utama API**

            1. **PoP & Distribution Management**
            * **Get Mini PoP Topology:** Menyediakan visibilitas *real-time* terhadap hierarki, status perangkat, dan skema topologi Mini PoP yang terhubung.
            * **Confirm Network Assignment:** Memproses konfirmasi penugasan jaringan, sinkronisasi *port*, serta validasi alokasi *bandwidth/VLAN* secara otomatis dari sistem eksternal ke infrastruktur Whusnet.

            ---

            ### **Standar Integrasi & Keamanan**

            * **Base URL:** `{app-url}/api/v1`
            * **Format Data:** Semua *request* dan *response* menggunakan format **JSON** standar (`application/json`).
            * **Autentikasi:** Seluruh *endpoint* terlindungi oleh mekanis otorisasi *Bearer Token (JWT)*. Pastikan `Authorization Header` dilampirkan pada setiap panggilan API.
            * **HTTP Status Code Standard:**
            * `200 OK` — Permintaan berhasil diproses.
            * `400 Bad Request` — Format *payload* atau parameter input tidak valid.
            * `401 Unauthorized` — Kredensial atau token API tidak valid / kedaluwarsa.
            * `500 Internal Server Error` — Terjadi kesalahan internal pada server Whusnet.

            ---

            > **Catatan Penting:** 
            > Untuk arsitektur sistem tingkat lanjut, batasan tingkat layanan (*rate limiting*), serta rancangan alur *web-hook* lengkap, silakan merujuk pada **Dokumen Rancangan Arsitektur Jaringan Whusnet** resmi.

            MARKDOWN,
    ],

    'ui' => [
        'title' => 'Whusnet Operasional API',
    ],

    /*
     * Load Scramble's development tools on documentation pages. An explicit
     * SCRAMBLE_DEV_TOOLS value takes precedence over APP_DEBUG.
     */
    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        /*
         * Stoplight Elements config options: https://docs.stoplight.io/docs/elements/b074dc47b2826-elements-configuration-options
         */
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        /*
         * Scalar API reference config options: https://scalar.com/products/api-references/configuration
         */
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
     * Automatically document API security (OpenAPI `security` / `securitySchemes`) based on route
     * middleware.
     *
     * Disabled by default. Uncomment the line below to enable `MiddlewareAuthSecurityStrategy`.
     * When at least one documented route uses middleware matching the configured patterns (by default
     * `auth` and `auth:*`), bearer auth is applied globally. Routes without matching middleware are
     * marked as public (`security: []`).
     *
     * Set to `null` explicitly to disable. If you already configure security manually via
     * `afterOpenApiGenerated` / `extendOpenApi`, keep this disabled to avoid duplicate schemes.
     *
     * Customize with a class-string or [class, options]:
     *
     * 'security_strategy' => [
     *     \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
     *     [
     *         'middleware' => ['auth', 'auth:*'],
     *         'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer'),
     *     ],
     * ],
     */
    // 'security_strategy' => \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
    'security_strategy' => null,
];
