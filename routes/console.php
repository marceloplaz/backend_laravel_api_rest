<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Registro de tu comando personalizado
Artisan::command('app:sincronizar-personal', function () {
    // Usamos $this->call para que Laravel maneje la salida correctamente
    $this->call('app:sincronizar-personal');
})->purpose('Sincroniza datos de personal desde SQL Server a MySQL');