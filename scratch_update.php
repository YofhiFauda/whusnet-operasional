<?php

$files = [
    'CustomerController.php',
    'CustomerDocumentController.php',
    'CustomerReportController.php',
    'DashboardController.php',
    'InvoiceController.php',
    'InvoiceReportController.php',
    'PaymentController.php',
    'PaymentReportController.php'
];

foreach ($files as $file) {
    $path = 'd:/Whusnet/whusnet-operasional/app/Http/Controllers/' . $file;
    if (!file_exists($path)) continue;
    $content = file_get_contents($path);
    
    // We only want to replace forUser() that are called on Customer, Invoice, Payment or their queries.
    // Instead of regex which is tricky, let's just replace all ->forUser() and ::forUser()
    // BUT we must not replace Pop::forUser() or \App\Models\Pop::forUser().
    
    // First, temporarily hide Pop::forUser() and ->forUser() if it's related to Pop
    $content = str_replace('Pop::forUser(', 'POP_FOR_USER_PLACEHOLDER(', $content);
    $content = str_replace('\App\Models\Pop::forUser(', 'MODELS_POP_FOR_USER_PLACEHOLDER(', $content);
    
    // Now replace the rest
    $content = str_replace('forUser(', 'applyUserScope(', $content);
    
    // Restore Pop
    $content = str_replace('POP_FOR_USER_PLACEHOLDER(', 'Pop::forUser(', $content);
    $content = str_replace('MODELS_POP_FOR_USER_PLACEHOLDER(', '\App\Models\Pop::forUser(', $content);
    
    file_put_contents($path, $content);
    echo "Updated $file\n";
}
