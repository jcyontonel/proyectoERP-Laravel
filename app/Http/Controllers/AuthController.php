<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    public function showLogin()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Credenciales incorrectas'], 401);
            }
            return back()->with('error', 'Credenciales incorrectas');
        }

        // Login exitoso (session-based)
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            // Sólo si estás implementando login API aquí: crear token
            $token = $request->user()->createToken('api-token')->plainTextToken;
            return response()->json([
                'user' => $request->user(),
                'token' => $token,
            ]);
        }

        // Para web: redirigir al dashboard
        return redirect()->intended(route('dashboard'));
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
