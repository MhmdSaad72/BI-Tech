<?php

namespace App\Console\Commands;

use App\Services\Illuminate\ChallengeDatabase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;

#[Signature('illuminate:db:query {sql : The SQL statement to run}')]
#[Description('Run a read-only SQL statement against the challenge database through the SSH tunnel')]
class QueryChallengeDatabaseCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ChallengeDatabase $database): int
    {
        $rows = $database->using(fn (Connection $connection): array => $connection->transaction(function () use ($connection): array {
            $connection->statement('SET TRANSACTION READ ONLY');

            return $connection->select($this->argument('sql'));
        }));

        if ($rows === []) {
            $this->components->warn('No rows returned.');

            return self::SUCCESS;
        }

        $rows = array_map(fn (object $row): array => (array) $row, $rows);

        $this->table(array_keys($rows[0]), $rows);

        return self::SUCCESS;
    }
}
