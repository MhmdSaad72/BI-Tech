<?php

namespace App\Services\Illuminate;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Persists challenge secrets on the private disk, which writes files as 0600
 * and directories as 0700 — permissions OpenSSH requires for identity files.
 */
class CredentialStore
{
    private const string PRIVATE_KEY_PATH = 'illuminate/id_ssh';

    private const string CREDENTIALS_PATH = 'illuminate/credentials.json';

    public function __construct(private readonly Filesystem $disk) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload): ChallengeCredentials
    {
        $validated = Validator::make($payload, [
            'ssh.private_key' => ['required', 'string'],
            'ssh.host' => ['required', 'string'],
            'ssh.port' => ['required', 'integer'],
            'ssh.username' => ['required', 'string'],
            'database.host' => ['required', 'string'],
            'database.port' => ['required', 'integer'],
            'database.name' => ['required', 'string'],
            'database.username' => ['required', 'string'],
            'database.password' => ['required', 'string'],
        ])->validate();

        $this->disk->put(self::PRIVATE_KEY_PATH, Str::finish($validated['ssh']['private_key'], "\n"), 'private');
        $this->disk->put(self::CREDENTIALS_PATH, json_encode(Arr::except($validated, 'ssh.private_key'), JSON_PRETTY_PRINT), 'private');

        return $this->load();
    }

    public function load(): ChallengeCredentials
    {
        if (! $this->disk->exists(self::CREDENTIALS_PATH) || ! $this->disk->exists(self::PRIVATE_KEY_PATH)) {
            throw new RuntimeException('Challenge credentials not found. Run `php artisan illuminate:fetch-credentials` first.');
        }

        return ChallengeCredentials::fromPayload(
            json_decode($this->disk->get(self::CREDENTIALS_PATH), true, flags: JSON_THROW_ON_ERROR),
            $this->disk->path(self::PRIVATE_KEY_PATH),
        );
    }

    public function knownHostsPath(): string
    {
        return $this->disk->path('illuminate/known_hosts');
    }
}
