<?php

namespace App\Services\Illuminate;

use Closure;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Local port forwarding (`ssh -L`) to a service only reachable from the SSH host.
 */
class SshTunnel
{
    private ?InvokedProcess $process = null;

    public function __construct(
        private readonly ChallengeCredentials $credentials,
        private readonly string $knownHostsPath,
        private readonly int $connectTimeoutSeconds = 15,
    ) {}

    /**
     * Run the callback with the tunnel open, passing the local port it listens on.
     *
     * @template TReturn
     *
     * @param  Closure(int): TReturn  $callback
     * @return TReturn
     */
    public function run(Closure $callback): mixed
    {
        $localPort = $this->open();

        try {
            return $callback($localPort);
        } finally {
            $this->close();
        }
    }

    public function open(): int
    {
        $localPort = $this->findFreeLocalPort();

        $this->process = Process::forever()->start($this->command($localPort));

        $this->waitUntilListening($localPort);

        return $localPort;
    }

    public function close(): void
    {
        if ($this->process?->running()) {
            $this->process->stop(3, SIGTERM);
        }

        $this->process = null;
    }

    /**
     * @return list<string>
     */
    private function command(int $localPort): array
    {
        return [
            'ssh', '-N',
            '-L', "127.0.0.1:{$localPort}:{$this->credentials->databaseHost}:{$this->credentials->databasePort}",
            '-i', $this->credentials->privateKeyPath,
            '-p', (string) $this->credentials->sshPort,
            '-o', 'IdentitiesOnly=yes',
            '-o', 'BatchMode=yes',
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', "UserKnownHostsFile={$this->knownHostsPath}",
            '-o', "ConnectTimeout={$this->connectTimeoutSeconds}",
            '-o', 'ServerAliveInterval=15',
            "{$this->credentials->sshUsername}@{$this->credentials->sshHost}",
        ];
    }

    private function waitUntilListening(int $localPort): void
    {
        $deadline = microtime(true) + $this->connectTimeoutSeconds;

        while (microtime(true) < $deadline) {
            if (! $this->process->running()) {
                throw new RuntimeException('SSH tunnel exited: '.trim($this->process->errorOutput()));
            }

            $socket = @fsockopen('127.0.0.1', $localPort, $errorCode, $errorMessage, 0.2);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            Sleep::for(200)->milliseconds();
        }

        $this->close();

        throw new RuntimeException("SSH tunnel did not start listening on port {$localPort} within {$this->connectTimeoutSeconds}s.");
    }

    private function findFreeLocalPort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($server === false) {
            throw new RuntimeException("Unable to allocate a local port: {$errorMessage}");
        }

        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        fclose($server);

        return $port;
    }
}
