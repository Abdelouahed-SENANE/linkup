<?php

namespace App\Services\Interfaces;

interface UserServiceInterface
{

    public function all();
    public function destroy(string $id);
}
