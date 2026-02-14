<?php

use Illuminate\Support\Facades\Route;
use Mrzeroc\OvoApi\Laravel\Http\Controllers\OvoidTesterController;

if (! config('ovoid.tester.enabled', true)) {
    return;
}

$routePrefix = trim((string) config('ovoid.tester.route_prefix', 'ovoid'), '/');
$routeNamePrefix = (string) config('ovoid.tester.route_name_prefix', 'ovoid.');

Route::middleware('web')
    ->prefix($routePrefix)
    ->as($routeNamePrefix)
    ->group(function (): void {
        Route::get('/', [OvoidTesterController::class, 'index'])->name('index');
        Route::post('/execute/{feature}', [OvoidTesterController::class, 'execute'])->name('execute');
        Route::post('/reset', [OvoidTesterController::class, 'resetState'])->name('reset');
    });
