<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Suppression;
use App\Services\Crm\ContactDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = (string) $request->query('type', '');
        $stage = (string) $request->query('stage', '');
        $source = (string) $request->query('source', '');
        $dnc = (string) $request->query('dnc', '');

        $contacts = Contact::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('email', 'like', '%'.mb_strtolower($search).'%')
                ->orWhere('name', 'like', '%'.$search.'%')))
            ->when(array_key_exists($type, Contact::TYPES), fn ($q) => $q->where('type', $type))
            ->when(array_key_exists($stage, Contact::STAGES), fn ($q) => $q->where('stage', $stage))
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            ->when($dnc === '1', fn ($q) => $q->whereIn('email', Suppression::query()->select('email')))
            ->when($dnc === '0', fn ($q) => $q->whereNotIn('email', Suppression::query()->select('email')))
            ->orderByDesc('last_contact_at')
            ->orderBy('email')
            ->paginate(40)
            ->withQueryString();

        $suppressed = Suppression::query()
            ->whereIn('email', $contacts->pluck('email'))
            ->pluck('email')
            ->flip();

        return view('admin.contacts.index', [
            'contacts' => $contacts,
            'suppressed' => $suppressed,
            'sources' => Contact::query()->distinct()->orderBy('source')->pluck('source'),
            'filters' => compact('search', 'type', 'stage', 'source', 'dnc'),
            'totals' => [
                'contacts' => Contact::query()->count(),
                'suppressed' => Suppression::query()->count(),
            ],
        ]);
    }

    public function show(Contact $contact): View
    {
        $contact->load(['client', 'inquiries', 'quizLeads']);

        return view('admin.contacts.show', [
            'contact' => $contact,
            'suppression' => Suppression::query()->where('email', $contact->email)->first(),
        ]);
    }

    public function suppress(Request $request, Contact $contact, ContactDirectory $directory): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $directory->suppress($contact->email, 'manual', $data['reason'] ?? null);

        return back()->with('status', 'Marked do not contact. Outreach and marketing email to this address is now blocked.');
    }

    public function unsuppress(Contact $contact, ContactDirectory $directory): RedirectResponse
    {
        $directory->unsuppress($contact->email);

        return back()->with('status', 'Removed from the do-not-contact list.');
    }
}
