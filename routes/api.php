<?php

/*
|--------------------------------------------------------------------------
| Rutas API del dominio INAMAP
|--------------------------------------------------------------------------
|
| El core incluye este archivo dentro de su grupo `api` (prefijo `api/`,
| nombre `api.`). Cada archivo de routes/api/ se carga automáticamente.
|
*/

foreach (glob(base_path('domain/routes/api/*.php')) as $apiRoutesPath) {
    require $apiRoutesPath;
}
