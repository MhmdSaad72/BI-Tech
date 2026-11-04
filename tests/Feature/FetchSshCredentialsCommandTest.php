<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FetchSshCredentialsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.illuminate.url' => 'https://illuminate.test/api',
            'services.illuminate.token' => 'test-token',
        ]);

        Storage::fake('local');
    }

    public function test_it_stores_the_private_key_separately_from_the_credentials(): void
    {
        Http::fake(['illuminate.test/api/challenge/ssh-key' => Http::response($this->payload())]);

        $this->artisan('illuminate:fetch-credentials')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));

        Storage::disk('local')->assertExists('illuminate/id_ssh');
        $this->assertSame("-----BEGIN OPENSSH PRIVATE KEY-----\nkey\n-----END OPENSSH PRIVATE KEY-----\n", Storage::disk('local')->get('illuminate/id_ssh'));

        $credentials = json_decode(Storage::disk('local')->get('illuminate/credentials.json'), true);
        $this->assertArrayNotHasKey('private_key', $credentials['ssh']);
        $this->assertSame('secret', $credentials['database']['password']);
    }

    public function test_it_rejects_an_incomplete_payload_without_writing_anything(): void
    {
        $payload = $this->payload();
        unset($payload['database']['password']);

        Http::fake(['illuminate.test/api/challenge/ssh-key' => Http::response($payload)]);

        $this->expectException(ValidationException::class);

        try {
            $this->artisan('illuminate:fetch-credentials')->run();
        } finally {
            Storage::disk('local')->assertMissing('illuminate/id_ssh');
        }
    }

    /**
     * @return array{ssh: array<string, mixed>, database: array<string, mixed>}
     */
    private function payload(): array
    {
        return [
            'ssh' => [
                'private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nkey\n-----END OPENSSH PRIVATE KEY-----",
                'host' => 'illuminate.test',
                'port' => 22,
                'username' => 'illuminate',
            ],
            'database' => [
                'host' => 'localhost',
                'port' => 5432,
                'name' => 'illuminate_challenge',
                'username' => 'candidate',
                'password' => 'secret',
            ],
        ];
    }
}
