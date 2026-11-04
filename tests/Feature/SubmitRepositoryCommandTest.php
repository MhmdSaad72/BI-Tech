<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubmitRepositoryCommandTest extends TestCase
{
    private string $cvPath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.illuminate.url' => 'https://illuminate.test/api',
            'services.illuminate.token' => 'test-token',
        ]);

        $this->cvPath = sys_get_temp_dir().'/illuminate-cv-'.uniqid().'.pdf';
        file_put_contents($this->cvPath, "%PDF-1.4\n%%EOF\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->cvPath);

        parent::tearDown();
    }

    public function test_it_submits_the_repository_and_cv_as_multipart(): void
    {
        Http::fake(['illuminate.test/api/challenge/submit-repo' => Http::response(['message' => 'Received.'])]);

        $this->artisan('illuminate:submit-repo', [
            'repo_url' => 'https://github.com/mohamed/illuminate/',
            'cv' => $this->cvPath,
            '--force' => true,
        ])->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->isMultipart()
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && collect($request->data())->contains(fn (array $part): bool => $part['name'] === 'repo_url' && $part['contents'] === 'https://github.com/mohamed/illuminate')
            && collect($request->data())->contains(fn (array $part): bool => $part['name'] === 'cv' && $part['filename'] === basename($this->cvPath)));
    }

    public function test_it_rejects_a_non_pdf_cv_without_calling_the_api(): void
    {
        Http::fake();
        file_put_contents($this->cvPath, 'not a pdf');

        $this->artisan('illuminate:submit-repo', [
            'repo_url' => 'https://github.com/mohamed/illuminate',
            'cv' => $this->cvPath,
            '--force' => true,
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_rejects_a_repository_that_is_not_on_github(): void
    {
        Http::fake();

        $this->artisan('illuminate:submit-repo', [
            'repo_url' => 'https://gitlab.com/mohamed/illuminate',
            'cv' => $this->cvPath,
            '--force' => true,
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_reports_api_errors(): void
    {
        Http::fake(['illuminate.test/api/challenge/submit-repo' => Http::response(['message' => 'Already submitted.'], 422)]);

        $this->artisan('illuminate:submit-repo', [
            'repo_url' => 'https://github.com/mohamed/illuminate',
            'cv' => $this->cvPath,
            '--force' => true,
        ])
            ->expectsOutputToContain('Already submitted.')
            ->assertFailed();
    }
}
