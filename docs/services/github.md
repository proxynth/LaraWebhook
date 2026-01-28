# GitHub Integration

## Configuration

Add your GitHub webhook secret to `.env`:

```env
GITHUB_WEBHOOK_SECRET=your_github_webhook_secret_here
```

## Route Setup

```php
// routes/web.php
use App\Http\Controllers\GitHubWebhookController;

Route::post('/github-webhook', [GitHubWebhookController::class, 'handle'])
    ->middleware('validate-webhook:github');
```

## Controller Example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $event = $request->header('X-GitHub-Event');

        match ($event) {
            'push' => $this->handlePush($payload),
            'pull_request' => $this->handlePullRequest($payload),
            'issues' => $this->handleIssues($payload),
            'release' => $this->handleRelease($payload),
            'workflow_run' => $this->handleWorkflowRun($payload),
            'star' => $this->handleStar($payload),
            default => $this->handleUnknown($event),
        };

        return response()->json(['status' => 'success']);
    }

    private function handlePush(array $payload): void
    {
        $repository = $payload['repository']['full_name'];
        $branch = str_replace('refs/heads/', '', $payload['ref']);
        $commits = count($payload['commits']);

        Log::info('GitHub: Push event', [
            'repository' => $repository,
            'branch' => $branch,
            'commits' => $commits,
        ]);

        // Auto-deploy on main branch
        if ($branch === 'main') {
            Artisan::call('deploy:production');
        }
    }

    private function handlePullRequest(array $payload): void
    {
        $action = $payload['action'];
        $pr = $payload['pull_request'];

        Log::info("GitHub: PR {$action}", [
            'pr_number' => $pr['number'],
            'title' => $pr['title'],
            'author' => $pr['user']['login'],
        ]);

        if ($action === 'opened') {
            // Notify team about new PR
        }

        if ($action === 'closed' && $pr['merged']) {
            Log::info('GitHub: PR merged', [
                'pr_number' => $pr['number'],
                'merged_by' => $pr['merged_by']['login'] ?? 'unknown',
            ]);
        }
    }

    private function handleIssues(array $payload): void
    {
        $action = $payload['action'];
        $issue = $payload['issue'];

        Log::info("GitHub: Issue {$action}", [
            'issue_number' => $issue['number'],
            'title' => $issue['title'],
        ]);
    }

    private function handleRelease(array $payload): void
    {
        $action = $payload['action'];
        $release = $payload['release'];

        Log::info("GitHub: Release {$action}", [
            'tag' => $release['tag_name'],
            'name' => $release['name'],
        ]);

        if ($action === 'published') {
            // Deploy new release
            Artisan::call('deploy:production', [
                'version' => $release['tag_name'],
            ]);
        }
    }

    private function handleWorkflowRun(array $payload): void
    {
        $workflow = $payload['workflow_run'];

        Log::info('GitHub: Workflow ' . $workflow['conclusion'], [
            'workflow' => $workflow['name'],
            'conclusion' => $workflow['conclusion'],
        ]);
    }

    private function handleStar(array $payload): void
    {
        $action = $payload['action'];
        $stargazer = $payload['sender']['login'];
        $stars = $payload['repository']['stargazers_count'];

        Log::info('GitHub: Repository ' . ($action === 'created' ? 'starred' : 'unstarred'), [
            'stargazer' => $stargazer,
            'total_stars' => $stars,
        ]);
    }

    private function handleUnknown(string $event): void
    {
        Log::warning('GitHub: Unknown event', ['event' => $event]);
    }
}
```

## Signature Validation

GitHub uses `X-Hub-Signature-256` header with format:

```
sha256=HMAC_SHA256_SIGNATURE
```

LaraWebhook automatically validates this signature.

## Configure in GitHub

1. Go to your repository **Settings** → **Webhooks** → **Add webhook**
2. **Payload URL**: `https://your-domain.com/github-webhook`
3. **Content type**: `application/json`
4. **Secret**: Enter a strong secret
5. **Events**: Select events or "Send me everything"
6. Click **Add webhook**

## Testing

**Redeliver existing webhook:**
1. Go to **Settings** → **Webhooks**
2. Click on your webhook
3. Scroll to **Recent Deliveries**
4. Click **Redeliver**

**View logs:**
```bash
php artisan tinker
>>> \Proxynth\Larawebhook\Models\WebhookLog::where('service', 'github')->latest()->first();
```

## Common Events

| Event | Description |
|-------|-------------|
| `push` | Push to repository |
| `pull_request` | PR opened/closed/merged |
| `pull_request_review` | PR review submitted |
| `issues` | Issue opened/closed |
| `issue_comment` | Comment on issue |
| `release` | Release published |
| `workflow_run` | GitHub Actions workflow |
| `star` | Repository starred |
| `fork` | Repository forked |
