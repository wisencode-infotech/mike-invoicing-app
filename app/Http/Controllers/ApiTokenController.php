<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiTokenRequest;
use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function __construct(protected ApiTokenService $apiTokens) {}

    public function index(Request $request): View
    {
        return view('api-tokens.index', [
            'tokens' => $request->user()->apiTokens()->orderByDesc('id')->get(),
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $result = $this->apiTokens->create($request->user(), $request->validated('name'));

        return redirect()->route('api-tokens.index')
            ->with('status', 'api-token-created')
            ->with('plainTextToken', $result['plainTextToken']);
    }

    public function revoke(ApiToken $apiToken): RedirectResponse
    {
        $this->authorize('revoke', $apiToken);

        $this->apiTokens->revoke($apiToken);

        return redirect()->route('api-tokens.index')->with('status', 'api-token-revoked');
    }
}
