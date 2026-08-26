<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Symfony\Component\HttpFoundation\Response;

class AdminPasswordResetResponse implements PasswordResetResponseContract
{
    /**
     * Create a new response instance.
     */
    public function __construct(private readonly string $status) {}

    /**
     * Create the response for a reset admin password.
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => trans($this->status)])
            : to_route('admin.login')->with('status', trans($this->status));
    }
}
