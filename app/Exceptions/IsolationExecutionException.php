<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Gerbang eksekusi isolir dari Web A gagal — kasus tidak ketemu/tidak lagi
 * siap, atau layanan pelanggan tidak valid
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md).
 */
class IsolationExecutionException extends RuntimeException {}
