<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input
            label="Email"
            name="email"
            type="email"
            value="{{ old('email', $request->email) }}"
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
            autocomplete="new-password"
            class="mt-4"
        />
        @error('password') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror

        <x-input
            label="Confirm Password"
            name="password_confirmation"
            type="password"
            required
            autocomplete="new-password"
            class="mt-4"
        />

        <div class="mt-4 flex justify-end">
            <x-button type="submit" label="Reset Password" class="btn-primary" />
        </div>
    </form>
</x-guest-layout>
