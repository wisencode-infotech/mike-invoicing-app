<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanySettingsRequest;
use App\Services\CompanySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanySettingsController extends Controller
{
    public function __construct(protected CompanySettingsService $companySettings) {}

    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'settings' => $this->companySettings->getOrCreateForUser($request->user()),
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $this->companySettings->update(
            $request->user(),
            $request->settingsData(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return redirect()->route('settings.edit')->with('status', 'settings-updated');
    }
}
