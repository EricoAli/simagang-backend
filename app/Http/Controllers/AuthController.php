<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        // Validasi input
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed', // password_confirmation wajib dikirim
            'role' => 'required|string|in:mahasiswa,mitra,dosbing' // Admin tidak boleh register sendiri
        ]);

        // Buat user baru
        $user = User::create([
            'name' => $validatedData['name'],
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
            'role' => $validatedData['role']
        ]);

        // Buat token langsung setelah register
        $token = $user->createToken('api-token')->plainTextToken;
        $token_expired = \Carbon\Carbon::now()->addHours(2);

        $user->tokens->last()->update([
            'expires_at' => $token_expired,
        ]);

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $user,
            'role' => $user->role,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $token_expired,
        ], 201);
    }

    public function login(Request $request)
    {
        // Validasi input: gunakan username & password
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Coba login dengan username
        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json(['message' => 'Username atau password salah'], 401);
        }

        $user = $request->user();

        // Buat token
        $token = $user->createToken('api-token')->plainTextToken;

        // Tentukan kapan access_token expired (misalnya 2 jam)
        $token_expired = Carbon::now()->addHours(2);

        // Simpan tanggal expired ke token terakhir
        $user->tokens->last()->update([
            'expires_at' => $token_expired,
        ]);

        return response()->json([
            'access_token' => $token,
            'role' => $user->role,
            'user' => $user->load('mitra', 'mahasiswa'),
            'token_type' => 'Bearer',
            'expires_at' => $token_expired,
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke semua token milik user
        $request->user()->tokens->each(function ($token) {
            $token->delete();
        });

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
