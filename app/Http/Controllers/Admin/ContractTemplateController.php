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
        return view('admin.contract-templates.index', [
            'templates' => ContractTemplate::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.contract-templates.form', [
            'template' => new ContractTemplate,
            'availableVariables' => ContractVariableResolver::availableVariables(),
        ]);
    }

    public function store(StoreContractTemplateRequest $request): RedirectResponse
    {
        $template = DB::transaction(function () use ($request) {
            $template = ContractTemplate::create([
                'name' => $request->validated('name'),
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'body' => $request->validated('body'),
                'is_default' => (bool) $request->validated('is_default', false),
            ]);

            if ($template->is_default) {
                $this->clearOtherDefaults($template);
            }

            return $template;
        });

        return redirect()
            ->route('admin.contract-templates.index')
            ->with('status', 'Template "'.$template->name.'" saved.');
    }

    public function edit(ContractTemplate $contractTemplate): View
    {
        return view('admin.contract-templates.form', [
            'template' => $contractTemplate,
            'availableVariables' => ContractVariableResolver::availableVariables(),
        ]);
    }

    public function update(UpdateContractTemplateRequest $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        DB::transaction(function () use ($request, $contractTemplate) {
            $contractTemplate->update([
                'name' => $request->validated('name'),
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'body' => $request->validated('body'),
                'is_default' => (bool) $request->validated('is_default', false),
            ]);

            if ($contractTemplate->is_default) {
                $this->clearOtherDefaults($contractTemplate);
            }
        });

        return redirect()
            ->route('admin.contract-templates.index')
            ->with('status', 'Template updated.');
    }

    public function destroy(ContractTemplate $contractTemplate): RedirectResponse
    {
        $contractTemplate->delete();

        return redirect()
            ->route('admin.contract-templates.index')
            ->with('status', 'Template removed.');
    }

    private function clearOtherDefaults(ContractTemplate $keep): void
    {
        ContractTemplate::query()
            ->whereKeyNot($keep->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
