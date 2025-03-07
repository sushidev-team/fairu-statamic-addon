<?php

namespace SushidevTeam\Fairu;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Commands\Setup::class,
    ];

    public function bootAddon()
    {
        
    }
}
