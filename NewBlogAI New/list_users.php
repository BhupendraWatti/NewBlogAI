<?php

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$users = User::all();
echo "=== CURRENT USERS ===\n";
foreach ($users as $u) {
    echo "ID: {$u->id} | Name: {$u->name} | Email: {$u->email} | Role: {$u->role} | CustomerID: ".($u->customer_id ?? 'NULL')."\n";
}

$customers = Customer::all();
echo "\n=== CURRENT CUSTOMERS ===\n";
foreach ($customers as $c) {
    echo "ID: {$c->id} | Company: {$c->company_name} | Email: {$c->email}\n";
}
