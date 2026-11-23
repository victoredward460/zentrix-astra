<?php

namespace App\Http\Controllers\Agent;

use App\Enums\AgentMessageRole;
use App\Enums\AgentMessageStatus;
use App\Enums\AgentProviderFailure;
use App\Exceptions\AgentProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendAgentMessageRequest;
use App\Services\AI\AgentConversationService;
use App\Models\AgentThread;
use App\Models\SecurityFinding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\AI\OpenRouterEndpointDiscovery;

class AgentController extends Controller
{
    public function index(Request $request, AgentConversationService $conversationService): View
    {
        $user = $request->user();
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($validated['q'] ?? '');

        return view('agent.index', [
            'threads' => $this->threadList($request, $search),
            'search' => $search,
            'findings' => $user->securityFindings()->latest('detected_at')->limit(30)->get(),
            'providerConfigured' => $conversationService->isConfigured(),
            'model' => $user->aiModel(),
            'aiModels' => (array) config('ai.openrouter.models', []),
            'aiProviderConsent' => $user->hasAiProviderConsent(),
        ]);
    }

    public function updateModel(Request $request, OpenRouterEndpointDiscovery $endpointDiscovery): JsonResponse
    {
        $models = (array) config('ai.openrouter.models', []);
        $validated = $request->validate([
            'model' => ['required', 'string', Rule::in(array_keys($models))],
        ]);
        $model = $validated['model'];

        try {
            if (! in_array('nvidia', $endpointDiscovery->eligibleProviders($model), true)) {
                throw new AgentProviderException(AgentProviderFailure::ModelUnavailable);
            }
        } catch (AgentProviderException $exception) {
            return response()->json([
                'message' => $exception->userMessage(),
            ], 422);
        }

        $request->user()->forceFill(['ai_model' => $model])->save();

        return response()->json([
            'model' => $model,
            'label' => $models[$model],
            'message' => 'Model selection saved.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $thread = $request->user()->agentThreads()->create([
            'title' => 'New conversation',
            'last_activity_at' => now(),
        ]);

        return redirect()->route('agent.threads.show', $thread);
    }

    public function start(
        SendAgentMessageRequest $request,
        AgentConversationService $conversationService,
    ): RedirectResponse {
        if (! $conversationService->isConfigured()) {
            return back()
                ->withInput()
                ->with('agent_error', AgentProviderFailure::NotConfigured->userMessage());
        }

        if (! $request->user()->hasAiProviderConsent()) {
            return back()
                ->withInput()
                ->with('agent_error', 'Enable AI features in your profile before sending a prompt.');
        }

        $finding = $this->selectedFinding($request);
        $thread = $request->user()->agentThreads()->create([
            'title' => 'New conversation',
            'last_activity_at' => now(),
        ]);

        return $this->saveMessage($request, $thread, $conversationService, $finding);
    }

    public function show(Request $request, int $thread, AgentConversationService $conversationService): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($validated['q'] ?? '');
        $agentThread = $request->user()->agentThreads()->findOrFail($thread);
        $messages = $agentThread->messages()
            ->latest('id')
            ->paginate(50, ['*'], 'messages_page')
            ->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());
        $agentThread->setRelation('messages', $messages->getCollection());

        $user = $request->user();

        return view('agent.show', [
            'thread' => $agentThread,
            'messages' => $messages,
            'threads' => $this->threadList($request, $search),
            'search' => $search,
            'findings' => $user->securityFindings()->latest('detected_at')->limit(30)->get(),
            'providerConfigured' => $conversationService->isConfigured(),
            'model' => $user->aiModel(),
            'aiModels' => (array) config('ai.openrouter.models', []),
            'aiProviderConsent' => $user->hasAiProviderConsent(),
        ]);
    }

    public function send(
        SendAgentMessageRequest $request,
        int $thread,
        AgentConversationService $conversationService,
    ): RedirectResponse {
        $agentThread = $request->user()->agentThreads()->findOrFail($thread);

        if (! $conversationService->isConfigured()) {
            return back()
                ->withInput()
                ->with('agent_error', AgentProviderFailure::NotConfigured->userMessage());
        }

        if (! $request->user()->hasAiProviderConsent()) {
            return back()
                ->withInput()
                ->with('agent_error', 'Enable AI features in your profile before sending a prompt.');
        }

        $finding = $this->selectedFinding($request);

        return $this->saveMessage($request, $agentThread, $conversationService, $finding);
    }

    public function rename(Request $request, int $thread): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);
        $agentThread = $request->user()->agentThreads()->findOrFail($thread);
        $agentThread->update(['title' => trim($validated['title'])]);

        return redirect()
            ->route('agent.threads.show', $agentThread)
            ->with('status', 'Conversation renamed.');
    }

    public function destroy(Request $request, int $thread): RedirectResponse
    {
        $agentThread = $request->user()->agentThreads()->findOrFail($thread);
        $agentThread->delete();

        return redirect()
            ->route('agent.index')
            ->with('status', 'The conversation and its messages were deleted.');
    }

    private function saveMessage(
        SendAgentMessageRequest $request,
        AgentThread $agentThread,
        AgentConversationService $conversationService,
        ?SecurityFinding $finding,
    ): RedirectResponse {
        $user = $request->user();
        $model = $request->validated('model') ?? $user->aiModel();
        $user->forceFill(['ai_model' => $model])->save();
        $question = trim($request->validated('content'));
        $userMessage = $agentThread->messages()->create([
            'user_id' => $user->id,
            'role' => AgentMessageRole::User,
            'status' => AgentMessageStatus::Completed,
            'content' => $question,
        ]);

        if ($agentThread->title === 'New conversation') {
            $agentThread->title = mb_substr(preg_replace('/\s+/', ' ', $question) ?? $question, 0, 100);
        }

        try {
            $reply = $conversationService->reply(
                $agentThread,
                $question,
                $finding,
                $userMessage->id,
                $model,
            );
            $agentThread->messages()->create([
                'user_id' => $user->id,
                'role' => AgentMessageRole::Assistant,
                'status' => AgentMessageStatus::Completed,
                'content' => $reply->content,
                'provider' => $reply->provider,
                'model' => $reply->model,
                'prompt_tokens' => $reply->promptTokens,
                'completion_tokens' => $reply->completionTokens,
            ]);
        } catch (AgentProviderException $exception) {
            $agentThread->messages()->create([
                'user_id' => $user->id,
                'role' => AgentMessageRole::Assistant,
                'status' => AgentMessageStatus::Failed,
                'provider' => 'openrouter',
                'model' => $model,
                'error_code' => $exception->failure->value,
            ]);
        }

        $agentThread->last_activity_at = now();
        $agentThread->save();

        return redirect()->route('agent.threads.show', $agentThread);
    }

    private function selectedFinding(SendAgentMessageRequest $request): ?SecurityFinding
    {
        $finding = null;

        if ($request->filled('security_finding_id')) {
            $finding = $request->user()->securityFindings()->findOrFail((int) $request->input('security_finding_id'));
        }

        return $finding;
    }

    private function threadList(Request $request, string $search): LengthAwarePaginator
    {
        return $request->user()->agentThreads()
            ->withCount('messages')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('messages', fn ($messages) => $messages->where('content', 'like', '%'.$search.'%'));
                });
            })
            ->latest('last_activity_at')
            ->paginate(20)
            ->withQueryString();
    }
}
