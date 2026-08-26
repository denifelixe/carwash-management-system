<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class AdminRegisterResponse implements RegisterResponseContract
{
    /**
     * Create the response for a registered admin.
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(status: Response::HTTP_CREATED)
            : redirect()->intended(route('admin.dashboard', absolute: false));
    }
}
