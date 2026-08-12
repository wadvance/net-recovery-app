<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GeneratePerformanceReports;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reportes de desempeño programados:
//  - fin de día (diario)
//  - fin de semana (domingo)
//  - fin de mes (último día del mes) → reporte "a los 30 días"
Schedule::command(GeneratePerformanceReports::class, ['--period' => 'daily'])
    ->dailyAt('19:00')
    ->withoutOverlapping();

Schedule::command(GeneratePerformanceReports::class, ['--period' => 'weekly'])
    ->weeklyOn(0, '20:00')
    ->withoutOverlapping();

Schedule::command(GeneratePerformanceReports::class, ['--period' => 'monthly'])
    ->lastDayOfMonth('20:00')
    ->withoutOverlapping();
