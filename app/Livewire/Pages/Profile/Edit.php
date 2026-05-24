<?php

namespace App\Livewire\Pages\Profile;

use App\Services\SyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $delete_password = '';

    // Server connection fields (mobile only)
    public string $server_email = '';

    public string $server_password = '';

    public bool $isRunningInNative = false;

    public bool $isConnectedToServer = false;

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;

        $this->isRunningInNative = function_exists('nativephp_call');

        if ($this->isRunningInNative) {
            $this->isConnectedToServer = app(SyncService::class)->hasToken();
            $this->server_email = auth()->user()->email;
        }
    }

    public function updateProfile(): void
    {
        $user = auth()->user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->fill(['name' => $this->name, 'email' => $this->email]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        session()->flash('profile_success', 'Profile updated successfully.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        auth()->user()->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        session()->flash('password_success', 'Password updated successfully.');
    }

    public function deleteAccount(): void
    {
        $this->validate([
            'delete_password' => ['required', 'current_password'],
        ]);

        $user = auth()->user();

        Auth::logout();

        $user->delete();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }

    public function connectToServer(SyncService $syncService): void
    {
        $this->validate([
            'server_email' => ['required', 'email'],
            'server_password' => ['required', 'string'],
        ]);

        $serverUrl = rtrim(config('services.sync_server.url', ''), '/');

        if (empty($serverUrl)) {
            session()->flash('server_error', 'Server URL is not configured.');

            return;
        }

        $response = Http::timeout(15)
            ->post("{$serverUrl}/api/v1/auth/token", [
                'email' => $this->server_email,
                'password' => $this->server_password,
            ]);

        if (! $response->successful()) {
            session()->flash('server_error', 'Invalid credentials or server unreachable.');

            return;
        }

        $syncService->storeToken($response->json('token'));

        $this->isConnectedToServer = true;
        $this->reset('server_password');

        session()->flash('server_success', 'Connected to server successfully.');
    }

    public function disconnectFromServer(SyncService $syncService): void
    {
        $syncService->clearToken();
        $this->isConnectedToServer = false;

        session()->flash('server_success', 'Disconnected from server.');
    }

    public function render()
    {
        return view('livewire.pages.profile.edit');
    }
}
