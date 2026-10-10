<?php

return [
    'errors' => [
        'tenant_required'          => 'A valid tenant within your scope is required.',
        'layout_not_found'         => 'The layout does not exist or does not belong to this inamap.',
        'polygon_not_simple'       => 'The polygon needs at least 3 vertices, a non-zero area and no self-intersections.',
        'zone_overlap'             => 'The zone overlaps: :zones.',
        'structure_not_in_layout'  => 'The structure does not belong to this layout.',
        'category_not_found'       => 'The category does not exist or is not visible to this tenant.',
        'category_parent_invalid'  => 'Invalid parent category.',
        'category_in_use'          => 'The category has locations assigned; reassign them before deleting it.',
        'path_layout_immutable'    => 'A path cannot move to another layout.',
        'link_paths_invalid'       => 'Both paths must exist and belong to the same inamap.',
        'link_same_path'           => 'A link must join two different paths.',
        'vertical_same_layout'     => 'A vertical link joins paths on different layouts.',
        'link_cross_layout'        => 'Use a vertical link to join different layouts.',
        'vertical_type_required'   => 'Choose the vertical link type (elevator, ramp, stairs or escalator).',
        'item_not_in_layout'       => 'The item does not belong to this layout.',
        'plan_limit'               => 'Your plan allows :limit :resource.',
        'INAMAP_NOT_PUBLISHED'     => 'This map is not available.',
        'UNREACHABLE'              => 'There is no route between the origin and the destination.',
        'UNREACHABLE_ACCESSIBLE'   => 'There is no accessible route between the origin and the destination.',
        'NO_PATH_NEAR_ENDPOINT'    => 'The origin or destination has no nearby path.',
        'ENDPOINT_NOT_FOUND'       => 'The origin or destination does not exist on this map.',
        'TOKEN_REQUIRED'           => 'Scan the venue QR code to view this map.',
        'TOKEN_EXPIRED'            => 'This link has expired. Scan a new code.',
        'TOKEN_INVALID'            => 'This link is not valid for this map.',
        'ACCESS_KEY_INVALID'       => 'Invalid device credential.',
        'PLAN_LIMIT_REACHED'       => 'Plan limit reached.',
    ],

    'warnings' => [
        'structure_overlap' => 'Structure ":a" overlaps ":b".',
        'outside_layout'    => 'Some geometry lies outside the layout bounds.',
    ],

    'limits' => [
        'max_inamaps'               => 'inamaps',
        'max_layouts_per_inamap'    => 'layouts per inamap',
        'max_locations'             => 'locations',
        'max_active_route_sessions' => 'active route sessions',
    ],
];
