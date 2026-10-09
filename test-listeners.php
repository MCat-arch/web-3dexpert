<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

echo "Checking Registered event listeners...\n";
foreach (Event::getRawListeners() as $event => $listeners) {
    if (str_contains($event, 'Registered')) {
        echo "Event: $event\n";
        foreach ($listeners as $listener) {
            echo "  Listener: $listener\n";
        }
    }
}

echo "\nCustomer implements MustVerifyEmail: ";
echo (new ReflectionClass(App\Models\Customer::class))->implementsInterface(Illuminate\Contracts\Auth\MustVerifyEmail::class) ? 'YES' : 'NO';
echo "\n";