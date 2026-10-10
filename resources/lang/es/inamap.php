<?php

return [
    'errors' => [
        'tenant_required'          => 'Debe indicar un tenant válido dentro de su alcance.',
        'layout_not_found'         => 'El layout no existe o no pertenece a este inamap.',
        'polygon_not_simple'       => 'El polígono debe tener al menos 3 vértices, área y no cruzarse consigo mismo.',
        'zone_overlap'             => 'La zona se solapa con: :zones.',
        'structure_not_in_layout'  => 'La estructura no pertenece a este layout.',
        'category_not_found'       => 'La categoría no existe o no es visible para este tenant.',
        'category_parent_invalid'  => 'La categoría padre no es válida.',
        'category_in_use'          => 'La categoría tiene ubicaciones asignadas; reasígnelas antes de eliminarla.',
        'path_layout_immutable'    => 'Un camino no puede cambiar de layout.',
        'link_paths_invalid'       => 'Ambos caminos deben existir y pertenecer al mismo inamap.',
        'link_same_path'           => 'Un enlace debe unir dos caminos distintos.',
        'vertical_same_layout'     => 'Un enlace vertical une caminos de layouts distintos.',
        'link_cross_layout'        => 'Para unir layouts distintos use un enlace vertical.',
        'vertical_type_required'   => 'Indique el tipo de enlace vertical (ascensor, rampa, escalera o escalera mecánica).',
        'item_not_in_layout'       => 'El elemento no pertenece a este layout.',
        'plan_limit'               => 'Su plan permite :limit :resource.',
        'INAMAP_NOT_PUBLISHED'     => 'Este mapa no está disponible.',
        'UNREACHABLE'              => 'No hay una ruta disponible entre el origen y el destino.',
        'UNREACHABLE_ACCESSIBLE'   => 'No hay una ruta accesible entre el origen y el destino.',
        'NO_PATH_NEAR_ENDPOINT'    => 'El origen o el destino no tiene un camino cercano.',
        'ENDPOINT_NOT_FOUND'       => 'El origen o el destino no existe en este mapa.',
        'TOKEN_REQUIRED'           => 'Escanee el código QR del recinto para ver este mapa.',
        'TOKEN_EXPIRED'            => 'Este enlace venció. Escanee un código nuevo.',
        'TOKEN_INVALID'            => 'El enlace no es válido para este mapa.',
        'ACCESS_KEY_INVALID'       => 'Credencial de dispositivo inválida.',
        'PLAN_LIMIT_REACHED'       => 'Se alcanzó el límite del plan.',
    ],

    'warnings' => [
        'structure_overlap' => 'La estructura ":a" se solapa con ":b".',
        'outside_layout'    => 'Hay geometría fuera de los límites del layout.',
    ],

    'limits' => [
        'max_inamaps'               => 'inamaps',
        'max_layouts_per_inamap'    => 'layouts por inamap',
        'max_locations'             => 'ubicaciones',
        'max_active_route_sessions' => 'sesiones de ruta activas',
    ],
];
