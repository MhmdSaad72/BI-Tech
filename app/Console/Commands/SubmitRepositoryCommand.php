<?php

namespace App\Console\Commands;

use App\Services\Illuminate\IlluminateClient;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Validator;

#[Signature('illuminate:submit-repo
    {repo_url : Public URL of the GitHub repository with the solution}
    {cv : Path to your CV (PDF)}
    {--force : Submit without asking for confirmation}')]
#[Description('Submit the solution repository and CV to complete the challenge')]
class SubmitRepositoryCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(IlluminateClient $client): int
    {
        $repositoryUrl = rtrim($this->argument('repo_url'), '/');
        $cvPath = $this->argument('cv');

        $validator = Validator::make(['repo_url' => $repositoryUrl, 'cv' => $cvPath], [
            'repo_url' => ['required', 'url:https', 'regex:#^https://github\.com/[\w.-]+/[\w.-]+$#'],
            'cv' => ['required', function (string $attribute, string $path, Closure $fail): void {
                if (! is_file($path) || ! is_readable($path)) {
                    $fail("The CV file [{$path}] does not exist or is not readable.");
                } elseif (file_get_contents($path, length: 5) !== '%PDF-') {
                    $fail("The CV file [{$path}] is not a PDF.");
                }
            }],
        ], [
            'repo_url.regex' => 'The repository URL must look like https://github.com/{owner}/{repository}.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Repository', $repositoryUrl);
        $this->components->twoColumnDetail('CV', realpath($cvPath));

        if (! $this->option('force') && ! $this->confirm('Submit? This can only be done once.')) {
            $this->components->warn('Submission cancelled.');

            return self::FAILURE;
        }

        try {
            $response = $client->submitRepository($repositoryUrl, $cvPath);
        } catch (RequestException $exception) {
            $this->components->error(sprintf(
                'Submission failed (HTTP %d): %s',
                $exception->response->status(),
                $exception->response->json('message') ?? $exception->response->body(),
            ));

            return self::FAILURE;
        }

        $this->components->info($response['message'] ?? 'Submission received.');

        return self::SUCCESS;
    }
}
