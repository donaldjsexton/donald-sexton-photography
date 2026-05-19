<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isEditable();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isEditable();
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT], true);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $invoice->status !== Invoice::STATUS_VOID;
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return ! in_array($invoice->status, [Invoice::STATUS_VOID, Invoice::STATUS_DRAFT], true)
            && $invoice->amountDueCents() > 0;
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
