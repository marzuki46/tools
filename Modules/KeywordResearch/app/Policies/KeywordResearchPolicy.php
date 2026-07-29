<?php

namespace Modules\KeywordResearch\Policies;

use App\Models\User;
use Modules\KeywordResearch\Models\KeywordResearch;

class KeywordResearchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KeywordResearch $research): bool
    {
        return $research->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, KeywordResearch $research): bool
    {
        return $research->user_id === $user->id;
    }

    public function delete(User $user, KeywordResearch $research): bool
    {
        return $research->user_id === $user->id;
    }
}
