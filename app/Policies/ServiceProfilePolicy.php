<?php

namespace App\Policies;

use App\Models\ServiceProfile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceProfilePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        return true; // Allow users to view their own profiles
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceProfile $serviceProfile)
    {
        return $user->id === $serviceProfile->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        return true; // Allow authenticated users to create profiles
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceProfile $serviceProfile)
    {
        return $user->id === $serviceProfile->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceProfile $serviceProfile)
    {
        return $user->id === $serviceProfile->user_id;
    }
}
