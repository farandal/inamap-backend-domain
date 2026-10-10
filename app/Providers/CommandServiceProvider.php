<?php

namespace Domain\App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadCommands();
    }

    /**
     * Load the commands
     *
     * @return void
     */
    protected function loadCommands()
    {
        $commandsPath = base_path('domain/app/Console/Commands');

        // Domain command classes are optional in some client repositories.
        if (!File::isDirectory($commandsPath)) {
            return;
        }

        $commands = collect(File::files($commandsPath))
            ->map(function ($file) {
                $basename = $file->getBasename('.php');

                $command = "Domain\App\Console\Commands\\$basename";

                return $command;
            })
            ->toArray();

        $this->commands($commands);
    }
}
