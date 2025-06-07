<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LocationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        // Allow viewing locations if user is authenticated
        // Adjust to allow depots without service profiles if needed
        return true; // Or $user->serviceProfiles()->exists();
    }

    public function view(User $user, Location $location)
    {
        // Allow viewing if location is tied to a user-owned service profile or user-owned
        return $location->service_profile_id
            ? $user->serviceProfiles()->where('id', $location->service_profile_id)->exists()
            : $user->id === $location->user_id;
    }

    public function create(User $user)
    {
        return true; // Allow creating locations for authenticated users
    }

    public function update(User $user, Location $location)
    {
        return $location->service_profile_id
            ? $user->serviceProfiles()->where('id', $location->service_profile_id)->exists()
            : $user->id === $location->user_id;
    }

    public function delete(User $user, Location $location)
    {
        return $location->service_profile_id
            ? $user->serviceProfiles()->where('id', $location->service_profile_id)->exists()
            : $user->id === $location->user_id;
    }
}