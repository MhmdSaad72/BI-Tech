<?php

namespace App\Services\Illuminate;

final readonly class ChallengeCredentials
{
    public function __construct(
        public string $sshHost,
        public int $sshPort,
        public string $sshUsername,
        public string $privateKeyPath,
        public string $databaseHost,
        public int $databasePort,
        public string $databaseName,
        public string $databaseUsername,
        public string $databasePassword,
    ) {}

    /**
     * @param  array{ssh: array{host: string, port: int, username: string}, database: array{host: string, port: int, name: string, username: string, password: string}}  $payload
     */
    public static function fromPayload(array $payload, string $privateKeyPath): self
    {
        return new self(
            sshHost: $payload['ssh']['host'],
            sshPort: (int) $payload['ssh']['port'],
            sshUsername: $payload['ssh']['username'],
            privateKeyPath: $privateKeyPath,
            databaseHost: $payload['database']['host'],
            databasePort: (int) $payload['database']['port'],
            databaseName: $payload['database']['name'],
            databaseUsername: $payload['database']['username'],
            databasePassword: $payload['database']['password'],
        );
    }
}
