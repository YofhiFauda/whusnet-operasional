<?php

use App\Models\Customer;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$customers = Customer::whereNotNull('cid')->get();
$updated = 0;
foreach ($customers as $customer) {
    if ($pop = $customer->pop) {
        $newCid = $pop->generateComplexCid($customer, $customer->distribution);
        if ($customer->cid !== $newCid) {
            $customer->updateQuietly(['cid' => $newCid]);
            $updated++;
        }
    }
}
echo "Updated $updated customers CIDs to remove suffix.\n";
