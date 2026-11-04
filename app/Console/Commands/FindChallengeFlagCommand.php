<?php

namespace App\Console\Commands;

use App\Services\Illuminate\ChallengeDatabase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

#[Signature('illuminate:db:find-flag {--pattern= : POSIX regex the flag must match (defaults to ILLUMINATE{...})}')]
#[Description('Scan every readable relation in the challenge database for a flag')]
class FindChallengeFlagCommand extends Command
{
    private const string DEFAULT_PATTERN = 'ILLUMINATE\{[^}]+\}';

    /**
     * Execute the console command.
     */
    public function handle(ChallengeDatabase $database): int
    {
        $matches = $database->using(fn (Connection $connection): Collection => $connection->transaction(function () use ($connection): Collection {
            $connection->statement('SET TRANSACTION READ ONLY');

            return $this->readableRelations($connection)
                ->flatMap(fn (string $relation): array => array_map(
                    fn (object $row): array => [$relation, $row->flag],
                    $connection->select(
                        "select distinct (regexp_matches(r::text, ?, 'g'))[1] as flag from {$relation} r",
                        [$this->option('pattern') ?: self::DEFAULT_PATTERN],
                    ),
                ));
        }));

        if ($matches->isEmpty()) {
            $this->components->error('No flag found in any readable relation.');

            return self::FAILURE;
        }

        $this->table(['Relation', 'Flag'], $matches->all());

        return self::SUCCESS;
    }

    /**
     * Tables, views and materialized views outside the system schemas that the current role can SELECT from.
     *
     * @return Collection<int, string>
     */
    private function readableRelations(Connection $connection): Collection
    {
        return collect($connection->select(<<<'SQL'
            select format('%I.%I', n.nspname, c.relname) as relation
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            where c.relkind in ('r', 'p', 'v', 'm', 'f')
              and n.nspname not in ('pg_catalog', 'information_schema')
              and n.nspname not like 'pg_toast%'
              and has_table_privilege(c.oid, 'SELECT')
            order by 1
            SQL))->pluck('relation');
    }
}
