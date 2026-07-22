<?php

namespace App\Services\Sync;

use App\Models\Device;
use Closure;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * One syncable entity's wire contract: its scoped source query (the Design 02
 * §4 scope matrix, expressed as code) and its projection allow-list (§7
 * outbound rules). Only fields declared here ever cross the wire — int ids,
 * tenant_id, and internal columns cannot leak by construction.
 */
class SyncEntityDefinition
{
    /**
     * @param  Closure(Device): Builder  $query  scoped base query (Eloquent or raw builder)
     * @param  array<string, string>  $columns  wire field => column, passed through verbatim
     * @param  array<string, string>  $integers  wire field => column, cast to int (money minor units, counters)
     * @param  array<string, array{0: string, 1: class-string}>  $references  wire field => [FK column, referenced model]
     * @param  array<string, array{0: string, 1: string}>  $morphs  wire field => [type column, id column]
     * @param  array<string, Closure(object): mixed>  $computed  wire field => fn (row) => value
     */
    public function __construct(
        public readonly string $table,
        public readonly Closure $query,
        public readonly array $columns = [],
        public readonly array $integers = [],
        public readonly array $references = [],
        public readonly array $morphs = [],
        public readonly array $computed = [],
        public readonly bool $inSnapshot = true,
        public readonly bool $pulled = true,
    ) {}
}
