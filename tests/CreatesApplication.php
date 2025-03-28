<?php

namespace ThinkNeverland\Tapped\Tests;

use Illuminate\Contracts\Console\Kernel;
use Orchestra\Testbench\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = new Application(
            $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
        );

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
