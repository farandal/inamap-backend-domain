<?php

/*
|--------------------------------------------------------------------------
| INAMAP — configuración del dominio
|--------------------------------------------------------------------------
|
| Cargado por el core como config('inamap.*'). Ver README §3.4–§3.7.
|
*/

return [

    /*
    | Unidades de medida. La geometría de cada inamap se guarda en su propia
    | unidad; `to_meters` convierte distancias para tiempos y tolerancias.
    | v1 habilita solo metros (S2). Agregar una unidad = agregar una entrada.
    */
    'default_unit' => 'm',

    'units' => [
        'm'  => ['to_meters' => 1.0,    'enabled' => true],
        'ft' => ['to_meters' => 0.3048, 'enabled' => false],
    ],

    /*
    | Tipos de enlace vertical (S5). `accessible=false` se excluye de las rutas
    | accesibles. `default_cost_m` es la penalización en metros equivalentes
    | cuando el enlace no define su propio costo.
    */
    'vertical_types' => [
        'elevator'  => ['accessible' => true,  'default_cost_m' => 15.0],
        'ramp'      => ['accessible' => true,  'default_cost_m' => 5.0],
        'stairs'    => ['accessible' => false, 'default_cost_m' => 8.0],
        'escalator' => ['accessible' => false, 'default_cost_m' => 6.0],
    ],

    // Costo por defecto (m) de un enlace horizontal explícito sin `cost`:
    // la distancia euclidiana entre sus extremos.
    'link_uses_distance_as_cost' => true,

    // Dos vértices a menos de esta distancia (en metros) son el mismo nodo.
    'snap_tolerance_m' => (float) env('INAMAP_SNAP_TOLERANCE_M', 0.30),

    // Velocidad peatonal por defecto (m/s) si el tenant no define la suya (S3).
    'default_walking_speed_mps' => 1.2,

    // Caché del grafo de rutas por inamap (segundos).
    'graph_cache_ttl' => (int) env('INAMAP_GRAPH_CACHE_TTL', 3600),

    'search' => [
        'default_limit'        => 20,
        'max_limit'            => 50,
        'similarity_threshold' => 0.2,
    ],

    /*
    | Sesiones de ruta (S4). El TTL por inamap vive en inamaps.settings;
    | este es el valor por defecto y el máximo permitido.
    */
    'route_sessions' => [
        'default_ttl_minutes' => 60,
        'max_ttl_minutes'     => 1440,
        // URL base del visor (inamap-app). El QR apunta a {app_url}/inamap/{id}#t={token}.
        'app_url' => env('INAMAP_APP_URL', env('FRONTEND_URL', 'http://localhost:5173')),
    ],

    'access_modes' => ['open', 'token_required'],

    // Límites por plan propios del dominio (el core no los cuenta; ver Plan\InamapPlanLimits).
    'plan_limit_defaults' => [
        'max_inamaps'               => 1,
        'max_layouts_per_inamap'    => 0,
        'max_locations'             => 0,
        'max_active_route_sessions' => 0,
    ],

    // Campos del formulario de límites del plan (System → Subscription Plans → Limits).
    // Sustituyen a los del core (que son de otros dominios: productos, notificaciones push)
    // para ESTE dominio; ver AppDomainServiceProvider::registerPlanLimitFormats().
    // Las claves max_inamaps... las cuenta InamapPlanLimits, no el core.
    'plan_limit_formats' => [
        [
            'id'            => 'max_tenants',
            'group'         => 'tenants',
            'tab'           => 'Tenants',
            'attribute'     => 'limits.max_tenants',
            'label'         => 'Max Tenants',
            'visible'       => true,
            'required'      => false,
            'type'          => 'integer',
            'editable'      => true,
            'rules'         => 'nullable|integer|min:-1',
            'default_value' => null,
            'description'   => 'Maximum number of tenants (clients) allowed. Leave empty, 0 or -1 for unlimited.',
        ],
        [
            'id'            => 'max_users_per_tenant',
            'group'         => 'tenants',
            'tab'           => 'Tenants',
            'attribute'     => 'limits.max_users_per_tenant',
            'label'         => 'Max Users per Tenant',
            'visible'       => true,
            'required'      => false,
            'type'          => 'integer',
            'editable'      => true,
            'rules'         => 'nullable|integer|min:-1',
            'default_value' => null,
            'description'   => 'Maximum number of users per tenant. Leave empty, 0 or -1 for unlimited.',
        ],
        [
            'id'            => 'max_inamaps',
            'group'         => 'inamaps',
            'tab'           => 'Inamaps',
            'attribute'     => 'limits.max_inamaps',
            'label'         => 'Max Inamaps',
            'visible'       => true,
            'required'      => false,
            'type'          => 'integer',
            'editable'      => true,
            'rules'         => 'nullable|integer|min:-1',
            'default_value' => null,
            'description'   => 'Maximum number of inamaps (maps) the account can create. Use 0 or -1 for unlimited. If left empty the default of 1 applies.',
        ],
        [
            'id'            => 'max_layouts_per_inamap',
            'group'         => 'inamaps',
            'tab'           => 'Inamaps',
            'attribute'     => 'limits.max_layouts_per_inamap',
            'label'         => 'Max Layouts per Inamap',
            'visible'       => true,
            'required'      => false,
            'type'          => 'integer',
            'editable'      => true,
            'rules'         => 'nullable|integer|min:-1',
            'default_value' => null,
            'description'   => 'Maximum number of layouts (floors / plans) in each inamap. Use 0 or -1 for unlimited (also the default when left empty).',
        ],
        [
            'id'            => 'max_locations',
            'group'         => 'inamaps',
            'tab'           => 'Inamaps',
            'attribute'     => 'limits.max_locations',
            'label'         => 'Max Locations',
            'visible'       => true,
            'required'      => false,
            'type'          => 'integer',
            'editable'      => true,
            'rules'         => 'nullable|integer|min:-1',
            'default_value' => null,
            'description'   => 'Maximum number of locations (places) across the account. Use 0 or -1 for unlimited (also the default when left empty).',
        ],
        [
            'id'            => 'max_active_route_sessions',
            'group'         => 'inamaps',
            'tab'           => 'Inamaps',
            'attribute'     => 'limits.max_active_route_sessions',
            'label'         => 'Max Active Route Sessions',
            'visible'       => true,
            'required'      => false,
            'type'          => 'integer',
            'editable'      => true,
            'rules'         => 'nullable|integer|min:-1',
            'default_value' => null,
            'description'   => 'Maximum simultaneous active route sessions (QR routes) per tenant. Use 0 or -1 for unlimited (also the default when left empty).',
        ],
    ],

    // Throttle de la API pública (peticiones por minuto, por IP).
    'public_rate_limit' => (int) env('INAMAP_PUBLIC_RATE_LIMIT', 120),
    'session_rate_limit' => (int) env('INAMAP_SESSION_RATE_LIMIT', 20),

    // Categorías sembradas en cada tenant nuevo.
    'default_categories' => [
        ['name' => 'Baños',                  'color' => '#1E88E5', 'icon' => 'wc'],
        ['name' => 'Accesos',                'color' => '#43A047', 'icon' => 'door_front'],
        ['name' => 'Servicios',              'color' => '#FB8C00', 'icon' => 'room_service'],
        ['name' => 'Salidas de emergencia',  'color' => '#E53935', 'icon' => 'emergency'],
        ['name' => 'Información',            'color' => '#8E24AA', 'icon' => 'info'],
    ],
];
