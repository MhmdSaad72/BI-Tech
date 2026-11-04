<?php

namespace App\Console\Commands;

use App\Services\Illuminate\CredentialStore;
use App\Services\Illuminate\IlluminateClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('illuminate:fetch-credentials')]
#[Description('Fetch the SSH private key and database credentials for the challenge')]
class FetchSshCredentialsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(IlluminateClient $client, CredentialStore $credentialStore): int
    {
        $credentials = $credentialStore->store($client->fetchSshCredentials());

        $this->components->info('Credentials stored.');
        $this->components->twoColumnDetail('SSH', "{$credentials->sshUsername}@{$credentials->sshHost}:{$credentials->sshPort}");
        $this->components->twoColumnDetail('Private key', $credentials->privateKeyPath);
        $this->components->twoColumnDetail('Database', "{$credentials->databaseName} @ {$credentials->databaseHost}:{$credentials->databasePort} (via SSH)");
        $this->components->twoColumnDetail('Database user', $credentials->databaseUsername);

        return self::SUCCESS;
    }
}
