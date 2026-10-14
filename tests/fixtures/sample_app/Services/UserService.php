<?php

namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getUser(int $id): object
    {
        return $this->userRepository->find($id);
    }

    public function createUser(string $name, string $email): object
    {
        return $this->userRepository->create($name, $email);
    }
}
