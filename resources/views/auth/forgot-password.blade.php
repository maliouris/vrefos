<x-guest-layout>
    @if (session('status'))
        <p class="text-success text-sm mb-4">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-input
            label="Email"
            name="email"
            type="email"
            value="{{ old('email') }}"
            required
            autofocus
        />
        @error('email') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror

        <div class="mt-4 flex justify-end">
            <x-button type="submit" label="Send Reset Link" class="btn-primary" />
        </div>
    </form>
</x-guest-layout>
