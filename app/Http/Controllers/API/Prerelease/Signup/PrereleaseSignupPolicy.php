<?php

namespace Domain\App\Http\Controllers\API\Prerelease\Signup;

use App\Models\User;
use Domain\App\Models\Prerelease\PrereleaseSignup;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Pre-release signups are only manageable by system admins.
 */
class PrereleaseSignupPolicy
{
    use HandlesAuthorization;

    public function manage(User $user, PrereleaseSignup $item)
    {
        return $user->isSystemAdmin();
    }

    public function create(User $user)
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user)
    {
        return $user->isSystemAdmin();
    }
}
