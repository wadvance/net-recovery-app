<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(['code' => 'TIGO'], ['name' => 'TIGO', 'settings' => ['whatsapp_template' => 'equipment_recovery_notification']]);
        Company::firstOrCreate(['code' => 'MAS_MOVIL'], ['name' => 'MAS MOVIL', 'settings' => ['whatsapp_template' => 'equipment_recovery_notification']]);
        Company::firstOrCreate(['code' => 'TELCA'], ['name' => 'TELCA', 'settings' => ['whatsapp_template' => 'equipment_recovery_notification']]);

        User::firstOrCreate(['email' => 'admin@recovery.local'], [
            'name' => 'Administrador', 'password' => bcrypt('password123'), 'role' => 'admin', 'is_active' => true
        ]);
        User::firstOrCreate(['email' => 'supervisor@recovery.local'], [
            'name' => 'Supervisor', 'password' => bcrypt('password123'), 'role' => 'supervisor', 'is_active' => true
        ]);
        foreach (['juan.perez@recovery.local' => 'Juan Pérez', 'maria.garcia@recovery.local' => 'María García', 'carlos.lopez@recovery.local' => 'Carlos López'] as $email => $name) {
            User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => bcrypt('password123'), 'role' => 'agent', 'is_active' => true]);
        }
    }
}