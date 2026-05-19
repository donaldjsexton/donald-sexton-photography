<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contract $contract): bool
    {
        return $contract->isEditable();
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $contract->isEditable();
    }

    public function send(User $user, Contract $contract): bool
    {
        return in_array($contract->status, [Contract::STATUS_DRAFT, Contract::STATUS_SENT], true);
    }

    public function sendProposal(User $user, Contract $contract): bool
    {
        if (! $contract->isProposal()) {
            return false;
        }

        if (! in_array($contract->status, [Contract::STATUS_DRAFT, Contract::STATUS_SENT], true)) {
            return false;
        }

        $invoice = $contract->invoice;

        if ($invoice === null) {
            return false;
        }

        if (in_array($invoice->status, [Invoice::STATUS_VOID, Invoice::STATUS_PAID, Invoice::STATUS_REFUNDED], true)) {
            return false;
        }

        return $invoice->amount_paid_cents < $invoice->total_cents || $invoice->total_cents === 0;
    }

    public function void(User $user, Contract $contract): bool
    {
        return $contract->status !== Contract::STATUS_VOID;
    }

    public function restore(User $user, Contract $contract): bool
    {
        return true;
    }

    public function forceDelete(User $user, Contract $contract): bool
    {
        return false;
    }
}
