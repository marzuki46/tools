<?php

namespace Modules\ContentGenerator\Policies;

use App\Models\User;
use Modules\ContentGenerator\Models\ContentGeneration;

class ContentGenerationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContentGeneration $generation): bool
    {
        return $generation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ContentGeneration $generation): bool
    {
        return $generation->user_id === $user->id;
    }

    public function delete(User $user, ContentGeneration $generation): bool
    {
        return $generation->user_id === $user->id;
    }

    public function retryPhase(User $user, ContentGeneration $generation): bool
    {
        return $generation->user_id === $user->id;
    }

    public function generateMeta(User $user, ContentGeneration $generation): bool
    {
        return $generation->user_id === $user->id;
    }

    public function feedback(User $user, ContentGeneration $generation): bool
    {
        return $generation->user_id === $user->id;
    }
}
