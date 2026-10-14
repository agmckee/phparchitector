<?php

namespace App\Repositories;

class UserRepository
{
    public function find(int $id): object
    {
        // Simulate database query
        return new \stdClass();
    }

    public function create(string $name, string $email): object
    {
        // Simulate database insert
        return new \stdClass();
    }
}
