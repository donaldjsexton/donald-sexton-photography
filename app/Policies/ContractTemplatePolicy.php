<?php

namespace App\Policies;

use App\Models\ContractTemplate;
use App\Models\User;

class ContractTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContractTemplate $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ContractTemplate $template): bool
    {
        return true;
    }

    public function delete(User $user, ContractTemplate $template): bool
    {
        return true;
    }
}
