<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\RoleAbilitiesService;  // Import the RoleAbilitiesService

class CheckAbility
{
    public function handle(Request $request, Closure $next, string $ability)
    {
        $user = Auth::user();  // Get the authenticated user
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized. Please log in.'], 401);
        }

        // Fetch role abilities using the RoleAbilitiesService
        $roleAbilities = RoleAbilitiesService::getAbilities($user->usertype);  // Get abilities based on the user's role

        // Log the user and their abilities for debugging
        \Log::info('Authenticated User:', ['user' => $user]);
        \Log::info('Abilities for user ' . $user->usertype, ['abilities' => $roleAbilities]);

        // Check if the user has the required ability or all abilities ('*')
        if (in_array('*', $roleAbilities) || in_array($ability, $roleAbilities)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden: Missing required ability.'], 403);
    }
}

