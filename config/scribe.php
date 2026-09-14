<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\removeStrategies;

// Si el paquete no está instalado (entorno de producción --no-dev), retornamos una configuración neutra
if (! class_exists('Knuckles\Scribe\Config\AuthIn')) {
    return [
        'title' => 'ABI-2026 API Documentation',
        'auth' => ['enabled' => false],
        'routes' => [],
    ];
}

return [
    'title' => 'ABI-2026 API Documentation',
    'description' => 'Documentacion automatica de los endpoints API del proyecto ABI-2026.',
    'intro_text' => <<<'INTRO'
Esta documentacion se genera automaticamente a partir de las rutas API de Laravel.

Los archivos publicados por Scribe viven en `docs/generated/api` y no deben editarse manualmente.
INTRO,
    'base_url' => env('APP_URL', 'http://localhost'),
    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [],
        ],
    ],
    'type' => 'static',
    'theme' => 'default',
    'static' => [
        'output_path' => 'docs/generated/api',
    ],
    'laravel' => [
        'add_routes' => false,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],
    'external' => [
        'html_attributes' => [],
    ],
    'try_it_out' => [
        'enabled' => false,
        'base_url' => null,
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],
    'auth' => [
        'enabled' => false,
        'default' => false,
        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{YOUR_AUTH_KEY}',
        'extra_info' => '',
    ],
    'example_languages' => [
        'bash',
        'javascript',
        'php',
    ],
    'postman' => [
        'enabled' => true,
        'overrides' => [],
    ],
    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => [],
        'generators' => [],
    ],
    'groups' => [
        'default' => 'API Endpoints',
        'order' => [],
    ],
    'logo' => false,
    'last_updated' => 'Last updated: {date:Y-m-d H:i:s}',
    'examples' => [
        'faker_seed' => 2026,
        'models_source' => ['factoryMake'],
    ],
    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => removeStrategies(
            Defaults::RESPONSES_STRATEGIES,
            [Strategies\Responses\ResponseCalls::class]
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],
    'database_connections_to_transact' => [],
    'fractal' => [
        'serializer' => null,
    ],
];
