<?php

declare(strict_types=1);

namespace App\Domains\Media\Policies;

use App\Core\Foundation\Policies\BasePolicy;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Enums\MediaCategory;
use App\Domains\Media\Models\Media;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;

final class MediaPolicy extends BasePolicy
{
    public function view(User $user, Media $media): bool
    {
        if (! $user->isAuthorized()) {
            return false;
        }

        $owner = $media->mediable;

        if (! $owner instanceof Model) {
            return false;
        }

        if ($owner instanceof User) {
            return $this->canViewUserMedia($user, $owner);
        }

        return $this->canViewOwner($user, $owner);
    }

    public function upload(User $user, Media $media, Model $owner, string $category): bool
    {
        if (! $user->isAuthorized()) {
            return false;
        }

        $mediaCategory = MediaCategory::tryFrom($category);

        if ($mediaCategory === null || ! $mediaCategory->allowsOwner($owner::class)) {
            return false;
        }

        if ($owner instanceof User) {
            return $user->is($owner)
                && $mediaCategory === MediaCategory::PROFILE_IMAGE
                    || ($user->is($owner) && $mediaCategory === MediaCategory::DOCUMENT);
        }

        return $this->canManageOwner($user, $owner);
    }

    public function update(User $user, Media $media): bool
    {
        if (! $user->isAuthorized()) {
            return false;
        }

        $owner = $media->mediable;

        if ($owner instanceof User) {
            return $user->is($owner)
                && in_array($media->category, [
                    MediaCategory::PROFILE_IMAGE->value,
                    MediaCategory::DOCUMENT->value,
                ], true);
        }

        return $owner instanceof Model && $this->canManageOwner($user, $owner);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }

    private function canViewUserMedia(User $actor, User $owner): bool
    {
        if ($actor->is($owner)) {
            return true;
        }

        if ($actor->hasRole('super_admin_root')) {
            return $actor->hasPermission('users.view');
        }

        return $actor->hasRole('super_admin')
            && $actor->hasPermission('users.view')
            && $owner->cooperative_id !== null
            && $actor->cooperatives()->whereKey($owner->cooperative_id)->exists();
    }

    private function canViewOwner(User $actor, Model $owner): bool
    {
        if (! $this->canAccessCooperative($actor, $owner)) {
            return false;
        }

        if ($owner instanceof Product) {
            return $actor->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
                'user',
            ]) && ($actor->hasRole('super_admin_root')
                || $actor->hasPermission('products.view'));
        }

        if ($owner instanceof Cooperative) {
            return $actor->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
                'user',
            ]) && ($actor->hasRole('super_admin_root')
                || $actor->hasPermission('cooperatives.view'));
        }

        return false;
    }

    private function canManageOwner(User $actor, Model $owner): bool
    {
        if (! $this->canAccessCooperative($actor, $owner)) {
            return false;
        }

        if ($owner instanceof Product) {
            return $actor->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
            ]) && ($actor->hasRole('super_admin_root')
                || $actor->hasPermission('products.update'));
        }

        if ($owner instanceof Cooperative) {
            return $actor->hasAnyRole([
                'super_admin_root',
                'super_admin',
                'cooperative_admin',
            ]) && ($actor->hasRole('super_admin_root')
                || $actor->hasPermission('cooperatives.update'));
        }

        return false;
    }

    private function canAccessCooperative(User $actor, Model $owner): bool
    {
        $cooperativeId = match (true) {
            $owner instanceof Product => (string) $owner->cooperative_id,
            $owner instanceof Cooperative => (string) $owner->getKey(),
            default => null,
        };

        if ($cooperativeId === null || $cooperativeId === '') {
            return false;
        }

        if ($actor->hasRole('super_admin_root')) {
            return true;
        }

        if ($actor->hasRole('super_admin')) {
            return $actor->cooperatives()->whereKey($cooperativeId)->exists();
        }

        return $actor->hasAnyRole(['cooperative_admin', 'user'])
            && $actor->cooperative_id !== null
            && (string) $actor->cooperative_id === $cooperativeId;
    }
}
