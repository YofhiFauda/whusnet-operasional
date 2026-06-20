<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$customers = \App\Models\Customer::whereNotNull('cid')->get();
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
