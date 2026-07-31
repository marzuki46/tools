<?php

namespace Modules\SeoCluster\Policies;

use App\Models\User;
use Modules\SeoCluster\Models\KeywordCluster;

class KeywordClusterPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KeywordCluster $cluster): bool
    {
        return $cluster->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, KeywordCluster $cluster): bool
    {
        return $cluster->user_id === $user->id;
    }

    public function delete(User $user, KeywordCluster $cluster): bool
    {
        return $cluster->user_id === $user->id;
    }
}
