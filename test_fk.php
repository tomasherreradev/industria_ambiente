<?php

use Illuminate\Support\Facades\DB;

$results = DB::select("SELECT conname, pg_get_constraintdef(c.oid) AS def
FROM pg_constraint c
JOIN pg_namespace n ON n.oid = c.connamespace
WHERE conrelid = 'cotio'::regclass AND contype = 'f'");

foreach ($results as $r) {
    echo $r->conname . ": " . $r->def . "\n";
}
