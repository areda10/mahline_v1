<?php
declare(strict_types=1);
namespace App\Domains\Identity\Users\Http\Controllers;
use App\Domains\Identity\Users\Http\Requests\UpdateOwnProfileRequest;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Identity\Users\Services\UserProfileService;
use App\Domains\Localization\Enums\MoroccanRegion;
use App\Domains\Media\Enums\MediaCategory;
use App\Domains\Media\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
final class UserProfileController
{
    public function __construct(
        private readonly UserProfileService $profiles,
        private readonly MediaService $media,
    ) {}
    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('viewOwnProfile', $user), 403);
        $user->load(['profileImage', 'primaryAddress.country']);
        return view('identity.profile.edit', [
            'user' => $user,
            'address' => $user->primaryAddress,
            'regions' => MoroccanRegion::values(),
        ]);
    }
    public function update(UpdateOwnProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $this->profiles->updateOwnProfile($user, $request->validated());
        return redirect()->route('backoffice.profile.edit')
            ->with('success', 'Vos informations personnelles ont été mises à jour.');
    }
    public function storePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('updateOwnProfile', $user), 403);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $this->media->upload($user, $user, $file, MediaCategory::PROFILE_IMAGE->value);
        return redirect()->route('backoffice.profile.edit')
            ->with('success', 'Votre photo de profil a été mise à jour.');
    }
    public function deletePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('updateOwnProfile', $user), 403);
        $photo = $user->profileImage()->firstOrFail();
        $this->media->delete($user, $photo);
        return redirect()->route('backoffice.profile.edit')
            ->with('success', 'Votre photo de profil a été supprimée.');
    }
}
