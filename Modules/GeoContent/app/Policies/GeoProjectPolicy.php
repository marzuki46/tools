<?php

namespace Modules\GeoContent\Policies;

use App\Models\User;
use Modules\GeoContent\Models\GeoProject;

class GeoProjectPolicy
{
    public function view(User $user, GeoProject $project): bool
    {
        return (int) $project->user_id === (int) $user->id;
    }

    public function update(User $user, GeoProject $project): bool
    {
        return (int) $project->user_id === (int) $user->id;
    }

    public function delete(User $user, GeoProject $project): bool
    {
        return (int) $project->user_id === (int) $user->id;
    }
}
