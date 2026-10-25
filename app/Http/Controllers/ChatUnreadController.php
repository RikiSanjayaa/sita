<?php

namespace App\Http\Controllers;

use App\Services\ChatUnreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatUnreadController extends Controller
{
    public function __invoke(Request $request, ChatUnreadService $unread): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $activeRole = $user->resolveActiveRole($request->session()->get('active_role'));

        return response()
            ->json(['hasUnreadMessages' => $unread->hasUnreadMessages($user, $activeRole)])
            ->header('Cache-Control', 'no-store, private');
    }
}
