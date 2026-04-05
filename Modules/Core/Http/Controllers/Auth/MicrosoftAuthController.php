<?php

namespace Modules\Core\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Modules\Core\Models\User;
use Modules\Core\Services\Auth\AzureRoleService;
use Illuminate\Support\Facades\Auth;
use Exception;

class MicrosoftAuthController extends Controller
{
    /**
     * Redirect the user to the Microsoft authentication page.
     * 
     * @return RedirectResponse
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email', 'offline_access', 'User.Read', 'GroupMember.Read.All'])
            ->redirect();
    }

    /**
     * Obtain the user information from Microsoft.
     * 
     * @param AzureRoleService $roleService
     * @return RedirectResponse
     */
    public function callback(AzureRoleService $roleService): RedirectResponse
    {
        try {
            $azureUser = Socialite::driver('azure')->user();

            // Find or create the local user by email (only in 'mysql' connection)
            $user = User::updateOrCreate([
                'email' => $azureUser->getEmail(),
            ], [
                'name' => $azureUser->getName(),
                'microsoft_id' => $azureUser->getId(),
                'azure_token' => $azureUser->token,
                'azure_refresh_token' => $azureUser->refreshToken ?? null,
                'azure_token_expires_at' => property_exists($azureUser, 'expiresIn') ? now()->addSeconds($azureUser->expiresIn) : null,
                'password' => $user->password ?? bcrypt(str()->random(16)), // Dummy password for new users
            ]);

            // Synchronize roles based on Azure Groups (if available in the token/user data)
            // Note: Depending on Socialite provider, you might need extra scopes for 'groups'
            $groups = $azureUser->user['groups'] ?? []; // Tentative access to groups
            $roleService->syncRolesFromAzure($user, $groups);

            Auth::login($user);

            return redirect()->intended('/admin');

        } catch (Exception $e) {
            return redirect('/admin/login')
                ->withErrors(['microsoft' => 'Inloggen via Microsoft is mislukt: ' . $e->getMessage()]);
        }
    }
}
