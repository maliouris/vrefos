<x-guest-layout>
    <p class="text-sm text-base-content/70 mb-4">
        This is a secure area of the application. Please confirm your password before continuing.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <x-mary-input
            label="Password"
            name="password"
            type="password"
            required
            autocomplete="current-password"
        />
        @error('password') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror

        <div class="mt-4 flex justify-end">
            <x-mary-button type="submit" label="Confirm" class="btn-primary" />
        </div>
    </form>
</x-guest-layout>
