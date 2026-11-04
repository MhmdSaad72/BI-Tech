<?php

namespace App\Services\Illuminate;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IlluminateClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly int $timeout = 15,
    ) {}

    /**
     * Fetch the SSH private key and database credentials issued for the current token.
     *
     * @return array<string, mixed>
     */
    public function fetchSshCredentials(): array
    {
        return $this->request()
            ->retry(2, 500, throw: false)
            ->get('challenge/ssh-key')
            ->throw()
            ->json();
    }

    /**
     * Submit the solution repository together with the candidate's CV.
     *
     * @return array<string, mixed>
     */
    public function submitRepository(string $repositoryUrl, string $cvPath): array
    {
        $cv = fopen($cvPath, 'r');

        try {
            return $this->request()
                ->attach('cv', $cv, basename($cvPath), ['Content-Type' => 'application/pdf'])
                ->post('challenge/submit-repo', ['repo_url' => $repositoryUrl])
                ->throw()
                ->json() ?? [];
        } finally {
            if (is_resource($cv)) {
                fclose($cv);
            }
        }
    }

    private function request(): PendingRequest
    {
        if (blank($this->token)) {
            throw new RuntimeException('ILLUMINATE_TOKEN is not configured.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson()
            ->timeout($this->timeout);
    }
}
