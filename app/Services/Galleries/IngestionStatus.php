<?php

namespace App\Services\Galleries;

/**
 * Outcome of a single photo ingestion attempt. Mirrors the Java engine's
 * auditable success / duplicate / failure semantics.
 */
enum IngestionStatus: string
{
    case Created = 'created';
    case Duplicate = 'duplicate';
    case Failed = 'failed';
}
