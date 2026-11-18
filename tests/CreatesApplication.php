<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $config = $app->make('config');
        if (! $config->get('app.key')) {
            $config->set('app.key', 'base64:'.base64_encode(Encrypter::generateKey($config->get('app.cipher'))));
        }

        return $app;
    }
}
