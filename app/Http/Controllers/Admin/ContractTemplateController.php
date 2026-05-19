<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContractTemplateRequest;
use App\Http\Requests\Admin\UpdateContractTemplateRequest;
use App\Models\ContractTemplate;
use App\Services\Contracts\ContractVariableResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContractTemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ContractTemplate::class);

        return view('admin.contract-templates.index', [
            'templates' => ContractTemplate::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ContractTemplate::class);

        return view('admin.contract-templates.form', [
            'template' => new ContractTemplate,
            'availableVariables' => ContractVariableResolver::availableVariables(),
        ]);
    }

    public function store(StoreContractTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', ContractTemplate::class);

        $template = DB::transaction(function () use ($request) {
            $makeDefault = (bool) $request->validated('is_default', false);

            if ($makeDefault) {
                ContractTemplate::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return ContractTemplate::create([
                'name' => $request->validated('name'),
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'body' => $request->validated('body'),
                'is_default' => $makeDefault,
            ]);
        });

        return redirect()
            ->route('admin.contract-templates.index')
            ->with('status', 'Template "'.$template->name.'" saved.');
    }

    public function edit(ContractTemplate $contractTemplate): View
    {
        $this->authorize('update', $contractTemplate);

        return view('admin.contract-templates.form', [
            'template' => $contractTemplate,
            'availableVariables' => ContractVariableResolver::availableVariables(),
        ]);
    }

    public function update(UpdateContractTemplateRequest $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->authorize('update', $contractTemplate);

        DB::transaction(function () use ($request, $contractTemplate) {
            $makeDefault = (bool) $request->validated('is_default', false);

            if ($makeDefault) {
                ContractTemplate::query()
                    ->whereKeyNot($contractTemplate->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $contractTemplate->update([
                'name' => $request->validated('name'),
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'body' => $request->validated('body'),
                'is_default' => $makeDefault,
            ]);
        });

        return redirect()
            ->route('admin.contract-templates.index')
            ->with('status', 'Template updated.');
    }

    public function destroy(ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->authorize('delete', $contractTemplate);

        $contractTemplate->delete();

        return redirect()
            ->route('admin.contract-templates.index')
            ->with('status', 'Template removed.');
    }
}
