<x-guest-layout>
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-input
            label="Email"
            name="email"
            type="email"
            value="{{ old('email') }}"
            required
            autofocus
            autocomplete="username"
        />
        @error('email') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror

        <x-input
            label="Password"
            name="password"
            type="password"
            required
            autocomplete="current-password"
            class="mt-4"
        />
        @error('password') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror

        <div class="flex items-center justify-between mt-4">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm link">Forgot password?</a>
            @endif
            <x-button type="submit" label="Log in" class="btn-primary" />
        </div>
    </form>
</x-guest-layout>
