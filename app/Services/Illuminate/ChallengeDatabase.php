<?php

namespace App\Services\Illuminate;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Opens an SSH tunnel to the challenge PostgreSQL server and exposes it as a
 * runtime database connection for the duration of a callback.
 */
class ChallengeDatabase
{
    private const string CONNECTION = 'illuminate';

    public function __construct(private readonly CredentialStore $credentialStore) {}

    /**
     * @template TReturn
     *
     * @param  Closure(Connection): TReturn  $callback
     * @return TReturn
     */
    public function using(Closure $callback): mixed
    {
        $credentials = $this->credentialStore->load();
        $tunnel = new SshTunnel($credentials, $this->credentialStore->knownHostsPath());

        return $tunnel->run(function (int $localPort) use ($credentials, $callback): mixed {
            config(['database.connections.'.self::CONNECTION => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => $localPort,
                'database' => $credentials->databaseName,
                'username' => $credentials->databaseUsername,
                'password' => $credentials->databasePassword,
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]]);

            try {
                return $callback(DB::connection(self::CONNECTION));
            } finally {
                DB::purge(self::CONNECTION);
            }
        });
    }
}
