@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <form method="POST" action="{{ route('login.post') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" novalidate>
        @csrf

        {{-- Error general (credenciales incorrectas) --}}
        @if ($errors->has('username') && ! $errors->has('password'))
            <p role="alert" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                {{ $errors->first('username') }}
            </p>
        @endif
         @if(session('error'))
            <p class="text-red-500 text-center mb-4">{{ session('error') }}</p>
        @endif

        <div class="space-y-4">
            <div>
                <label for="username" class="field-label">Nombre de usuario</label>
                <input id="username" name="email" type="email" value="{{ old('email') }}"
                    placeholder="Ingresa tu usuario" autocomplete="email" autocapitalize="none" required autofocus
                    @class(['field', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has('username')])
                    @if ($errors->has('email')) aria-invalid="true" @endif>
            </div>

            <div>
                <label for="password" class="field-label">Contraseña</label>
                <input id="password" name="password" type="password"
                    placeholder="Ingresa tu contraseña" autocomplete="current-password" required
                    @class(['field', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has('password')])
                    @if ($errors->has('password')) aria-invalid="true" @endif>
                @error('password')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex w-fit cursor-pointer items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                    class="size-4 rounded border-slate-300 accent-brand-600">
                Recordarme
            </label>

            <button type="submit" class="btn btn-primary w-full">Iniciar sesión</button>
        </div>
    </form>
@endsection