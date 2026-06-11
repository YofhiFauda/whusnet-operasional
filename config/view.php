<?php

$compiledPath = env('VIEW_COMPILED_PATH');

if ($compiledPath !== null && ! preg_match('/^(?:[A-Za-z]:[\/\\\\]|[\/\\\\])/', $compiledPath)) {
    $compiledPath = base_path($compiledPath);
}

return [
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => $compiledPath ?: realpath(storage_path('framework/views')),
];
