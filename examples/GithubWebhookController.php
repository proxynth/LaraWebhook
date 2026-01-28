<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * GitHub Webhook Controller Example
 *
 * This example shows how to handle GitHub webhooks with LaraWebhook,
 * including automatic retry logic for failed webhooks.
 *
 * Setup:
 * 1. Add to your .env:
 *    GITHUB_WEBHOOK_SECRET=your_github_webhook_secret
 *
 * 2. Add to config/larawebhook.php:
 *    'services' => [
 *        'github' => [
 *            'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
 *            'tolerance' => 300,
 *        ],
 *    ]
 *
 * 3. Add to routes/web.php:
 *    Route::post('/github-webhook', [GitHubWebhookController::class, 'handle'])
 *        ->middleware('validate-webhook:github');
 *
 * 4. Configure in GitHub:
 *    - Go to repository Settings → Webhooks → Add webhook
 *    - Payload URL: https://your-domain.com/github-webhook
 *    - Content type: application/json
 *    - Secret: Enter a strong secret and add to .env
 *    - Events: Select events or "Send me everything"
 */
class GitHubWebhookController extends Controller
{
    /**
     * Handle incoming GitHub webhook.
     *
     * The webhook is already validated by the 'validate-webhook:github' middleware.
     * LaraWebhook automatically logs the webhook and handles retries for failed events.
     */
    public function handle(Request $request): JsonResponse
    {
        // Get the webhook payload and event type
        $payload = json_decode($request->getContent(), true);
        $event = $request->header('X-GitHub-Event');
        $deliveryId = $request->header('X-GitHub-Delivery');

        Log::info('GitHub webhook received', [
            'event' => $event,
            'delivery_id' => $deliveryId,
            'repository' => $payload['repository']['full_name'] ?? 'unknown',
        ]);

        // Route to specific event handlers
        try {
            match ($event) {
                'push' => $this->handlePush($payload),
                'pull_request' => $this->handlePullRequest($payload),
                'pull_request_review' => $this->handlePullRequestReview($payload),
                'issues' => $this->handleIssues($payload),
                'issue_comment' => $this->handleIssueComment($payload),
                'release' => $this->handleRelease($payload),
                'workflow_run' => $this->handleWorkflowRun($payload),
                'deployment' => $this->handleDeployment($payload),
                'star' => $this->handleStar($payload),
                'fork' => $this->handleFork($payload),
                default => $this->handleUnknownEvent($event, $payload),
            };

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error processing GitHub webhook', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 500 to trigger GitHub's automatic retry mechanism
            // GitHub will retry the webhook up to 3 times with exponential backoff
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle push event.
     *
     * Triggered when commits are pushed to a repository.
     */
    private function handlePush(array $payload): void
    {
        $repository = $payload['repository']['full_name'];
        $branch = str_replace('refs/heads/', '', $payload['ref']);
        $commits = count($payload['commits']);
        $pusher = $payload['pusher']['name'];

        Log::info('Push event received', [
            'repository' => $repository,
            'branch' => $branch,
            'commits' => $commits,
            'pusher' => $pusher,
        ]);

        // Example: Auto-deploy to production when pushing to main
        if ($branch === 'main' && $repository === 'your-org/your-repo') {
            Log::info('Triggering production deployment');

            // Queue deployment job for async processing
            // dispatch(new DeployToProductionJob($payload));

            // Or run deployment command
            // Artisan::call('deploy:production', ['--branch' => $branch]);
        }

        // Example: Auto-deploy to staging when pushing to develop
        if ($branch === 'develop' && $repository === 'your-org/your-repo') {
            Log::info('Triggering staging deployment');
            // dispatch(new DeployToStagingJob($payload));
        }
    }

    /**
     * Handle pull request events.
     *
     * Triggered when a pull request is opened, closed, reopened, or synchronized.
     */
    private function handlePullRequest(array $payload): void
    {
        $action = $payload['action'];
        $pr = $payload['pull_request'];
        $repository = $payload['repository']['full_name'];

        Log::info('Pull request event', [
            'action' => $action,
            'pr_number' => $pr['number'],
            'title' => $pr['title'],
            'author' => $pr['user']['login'],
            'repository' => $repository,
        ]);

        match ($action) {
            'opened' => $this->handlePullRequestOpened($pr, $repository),
            'closed' => $this->handlePullRequestClosed($pr, $repository),
            'reopened' => $this->handlePullRequestReopened($pr, $repository),
            'synchronize' => $this->handlePullRequestSynchronize($pr, $repository),
            default => null,
        };
    }

    private function handlePullRequestOpened(array $pr, string $repository): void
    {
        Log::info('New pull request opened', [
            'pr_number' => $pr['number'],
            'title' => $pr['title'],
        ]);

        // Example: Send notification to Slack
        // Notification::route('slack', config('services.slack.webhook'))
        //     ->notify(new NewPullRequestNotification($pr));

        // Example: Auto-assign reviewers
        // $reviewers = $this->getAutoAssignReviewers($pr);
        // GitHubApi::assignReviewers($repository, $pr['number'], $reviewers);
    }

    private function handlePullRequestClosed(array $pr, string $repository): void
    {
        if ($pr['merged']) {
            Log::info('Pull request merged', [
                'pr_number' => $pr['number'],
                'merged_by' => $pr['merged_by']['login'] ?? 'unknown',
            ]);

            // Example: Send congratulations message
            // $this->sendMergeNotification($pr);
        } else {
            Log::info('Pull request closed without merge', [
                'pr_number' => $pr['number'],
            ]);
        }
    }

    private function handlePullRequestReopened(array $pr, string $repository): void
    {
        Log::info('Pull request reopened', [
            'pr_number' => $pr['number'],
        ]);
    }

    private function handlePullRequestSynchronize(array $pr, string $repository): void
    {
        Log::info('Pull request synchronized (new commits)', [
            'pr_number' => $pr['number'],
        ]);

        // Example: Trigger CI/CD pipeline
        // dispatch(new RunCIPipelineJob($pr));
    }

    /**
     * Handle pull request review events.
     */
    private function handlePullRequestReview(array $payload): void
    {
        $review = $payload['review'];
        $pr = $payload['pull_request'];

        Log::info('Pull request review submitted', [
            'pr_number' => $pr['number'],
            'reviewer' => $review['user']['login'],
            'state' => $review['state'], // 'approved', 'changes_requested', 'commented'
        ]);

        // Example: Auto-merge if approved by required reviewers
        if ($review['state'] === 'approved') {
            // $this->checkAndAutoMerge($pr);
        }
    }

    /**
     * Handle issues events.
     */
    private function handleIssues(array $payload): void
    {
        $action = $payload['action'];
        $issue = $payload['issue'];

        Log::info('Issue event', [
            'action' => $action,
            'issue_number' => $issue['number'],
            'title' => $issue['title'],
            'author' => $issue['user']['login'],
        ]);

        // Example: Auto-label issues based on content
        // if ($action === 'opened') {
        //     $labels = $this->detectIssueLabels($issue);
        //     GitHubApi::addLabels($repository, $issue['number'], $labels);
        // }
    }

    /**
     * Handle issue comment events.
     */
    private function handleIssueComment(array $payload): void
    {
        $action = $payload['action'];
        $comment = $payload['comment'];
        $issue = $payload['issue'];

        Log::info('Issue comment event', [
            'action' => $action,
            'issue_number' => $issue['number'],
            'commenter' => $comment['user']['login'],
        ]);
    }

    /**
     * Handle release events.
     */
    private function handleRelease(array $payload): void
    {
        $action = $payload['action'];
        $release = $payload['release'];

        Log::info('Release event', [
            'action' => $action,
            'tag' => $release['tag_name'],
            'name' => $release['name'],
        ]);

        if ($action === 'published') {
            Log::info('New release published', [
                'tag' => $release['tag_name'],
            ]);

            // Example: Deploy new release to production
            // dispatch(new DeployReleaseJob($release));

            // Example: Send release notification
            // Notification::route('slack', config('services.slack.webhook'))
            //     ->notify(new NewReleaseNotification($release));
        }
    }

    /**
     * Handle workflow run events.
     */
    private function handleWorkflowRun(array $payload): void
    {
        $workflow = $payload['workflow_run'];
        $conclusion = $workflow['conclusion']; // 'success', 'failure', 'cancelled', etc.

        Log::info('Workflow run event', [
            'workflow' => $workflow['name'],
            'status' => $workflow['status'],
            'conclusion' => $conclusion,
        ]);

        // Example: Send notification on workflow failure
        if ($conclusion === 'failure') {
            // Notification::route('slack', config('services.slack.webhook'))
            //     ->notify(new WorkflowFailedNotification($workflow));
        }
    }

    /**
     * Handle deployment events.
     */
    private function handleDeployment(array $payload): void
    {
        $deployment = $payload['deployment'];

        Log::info('Deployment event', [
            'environment' => $deployment['environment'],
            'ref' => $deployment['ref'],
        ]);
    }

    /**
     * Handle star events.
     */
    private function handleStar(array $payload): void
    {
        $action = $payload['action'];
        $repository = $payload['repository']['full_name'];
        $stargazer = $payload['sender']['login'];

        Log::info('Star event', [
            'action' => $action,
            'repository' => $repository,
            'stargazer' => $stargazer,
            'stars' => $payload['repository']['stargazers_count'],
        ]);

        // Example: Send thank you message or track stars
        if ($action === 'created') {
            // $this->sendThankYouMessage($stargazer);
        }
    }

    /**
     * Handle fork events.
     */
    private function handleFork(array $payload): void
    {
        $forkee = $payload['forkee'];
        $forker = $payload['sender']['login'];

        Log::info('Repository forked', [
            'forker' => $forker,
            'fork_url' => $forkee['html_url'],
        ]);
    }

    /**
     * Handle unknown event types.
     */
    private function handleUnknownEvent(string $event, array $payload): void
    {
        Log::warning('Unknown GitHub event type received', [
            'event_type' => $event,
        ]);
    }
}
