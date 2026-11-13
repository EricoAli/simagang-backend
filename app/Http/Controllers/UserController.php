<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function getUser(Request $request)
    {

        $user = $request->user();
        return response()->json($user, 200);
    }

    public function password(Request $request)
    {
        $rules = [
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Password tidak sesuai',
                'errors' => $validator->errors()
            ], 422);
        };

        $user = $request->user();

        if (!Hash::check($request->password_lama, $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai',
                'errors' => [
                    'password_lama' => ['Password lama tidak sesuai']
                ]
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diubah'
        ], 200);
    }
}
