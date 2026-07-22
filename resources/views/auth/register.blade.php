@extends('layouts.app')

@section('content')
<div class="page-container py-8">
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold mb-6 text-surface-900">Create Account</h1>

        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <form method="POST" action="/signup">
                @csrf

                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-surface-700 mb-2">Name</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100"
                    />
                    @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-surface-700 mb-2">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100"
                    />
                    @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-surface-700 mb-2">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100"
                    />
                    @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="password_confirmation" class="block text-sm font-medium text-surface-700 mb-2">Confirm Password</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100"
                    />
                </div>

                <button type="submit"
                    class="w-full rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-300 transition-colors">
                    Sign Up
                </button>
            </form>

            <p class="mt-4 text-sm text-surface-500 text-center">
                Already have an account?
                <a href="/login" class="text-primary-600 hover:text-primary-700">Log in</a>
            </p>
        </div>
    </div>
</div>
@endsection
