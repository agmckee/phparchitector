<?php

namespace App\Controllers;

use App\Services\UserService;
use Symfony\Component\HttpFoundation\Response;

class UserController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(): Response
    {
        return new Response('Hello');
    }

    public function show(int $id): Response
    {
        $user = $this->userService->getUser($id);
        return new Response($user->getName());
    }
}
