<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanySettingsService
{
    /**
     * Every user has exactly one settings row, created lazily on first use
     * (e.g. right after registration, before the owner has visited the
     * settings page).
     */
    public function getOrCreateForUser(User $user): CompanySetting
    {
        return $user->companySetting()->firstOrCreate([], [
            'company_name' => $user->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  Validated settings fields (see
     *                                      UpdateCompanySettingsRequest::settingsData()).
     */
    public function update(User $user, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): CompanySetting
    {
        $settings = $this->getOrCreateForUser($user);

        if ($logo) {
            $this->deleteLogoFile($settings);
            $data['logo_path'] = $logo->store('logos', 'public');
        } elseif ($removeLogo) {
            $this->deleteLogoFile($settings);
            $data['logo_path'] = null;
        }

        $settings->update($data);

        return $settings;
    }

    protected function deleteLogoFile(CompanySetting $settings): void
    {
        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }
    }
}
