<?php

namespace Domain\App\Console\Commands;

use Domain\App\Services\Inamap\Session\RouteSessionService;
use Illuminate\Console\Command;

class PruneRouteSessionsCommand extends Command
{
    protected $signature = 'inamap:prune-route-sessions';

    protected $description = 'Elimina sesiones de ruta vencidas hace más de un día';

    public function handle(RouteSessionService $sessions): int
    {
        $this->info('Sesiones eliminadas: ' . $sessions->pruneExpired());

        return self::SUCCESS;
    }
}
