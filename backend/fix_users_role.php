<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    $cols = Schema::getColumnListing('users');
    echo "Columnas actuales users: ".implode(', ', $cols)."\n<br>";
    if (!in_array('role', $cols)) {
        DB::statement("ALTER TABLE `users` ADD `role` VARCHAR(255) NOT NULL DEFAULT 'agent' AFTER `password`");
        echo "ADD role OK<br>";
    } else echo "role ya existe<br>";
    if (!in_array('phone', $cols)) {
        DB::statement("ALTER TABLE `users` ADD `phone` VARCHAR(255) NULL AFTER `email`");
        echo "ADD phone OK<br>";
    } else echo "phone ya existe<br>";
    if (!in_array('avatar', $cols)) {
        DB::statement("ALTER TABLE `users` ADD `avatar` VARCHAR(255) NULL AFTER `phone`");
        echo "ADD avatar OK<br>";
    } else echo "avatar ya existe<br>";
    if (!in_array('is_active', $cols)) {
        DB::statement("ALTER TABLE `users` ADD `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`");
        echo "ADD is_active OK<br>";
    } else echo "is_active ya existe<br>";
    if (!in_array('settings', $cols)) {
        DB::statement("ALTER TABLE `users` ADD `settings` JSON NULL AFTER `is_active`");
        echo "ADD settings OK<br>";
    } else echo "settings ya existe<br>";
    echo "DONE - users corregida<br>";
    echo "Columnas finales: ".implode(', ', Schema::getColumnListing('users'))."\n";
} catch (Exception $e) {
    echo "ERROR: ".$e->getMessage();
}
