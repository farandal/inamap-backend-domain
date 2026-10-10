<?php

/**
 * Ajustes de tenant del dominio INAMAP.
 *
 * AppDomainServiceProvider::mergeDomainTenantSettings() los anexa a
 * config('tenants.setting_formats'), así aparecen en la UI de ajustes del
 * tenant sin cambios en el frontend (módulo TenantSettings del core).
 *
 * El nombre del archivo importa: se registra por ruta en el provider.
 */
return [
    'encrypted_setting_keys' => [],

    'setting_formats' => [
        [
            'id'           => 'inamap_walking_speed_mps',
            'group'        => 'inamap',
            'tab'          => 'inamap',
            'attribute'    => 'settings.inamap_walking_speed_mps',
            'label'        => 'VELOCIDAD PEATONAL (m/s)',
            'visible'      => true,
            'required'     => false,
            'type'          => 'number',
            'editable'      => true,
            'rules'         => 'nullable|numeric|min:0.1|max:5',
            'default_value' => 1.2,
            'description'  => 'Velocidad usada para estimar el tiempo de las rutas. Por defecto 1.2 m/s.',
        ],
    ],

    'attribute_formats' => [],
];
