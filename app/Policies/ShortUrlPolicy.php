<?php

namespace App\Policies;

use App\Models\User;

class ShortUrlPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role?->name === 'SuperAdmin'
            || ($user->company()->exists() && in_array($user->role?->name, ['Admin', 'Member'], true));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user)
            && in_array($user->role?->name, ['Admin', 'Member'], true);
    }
}
