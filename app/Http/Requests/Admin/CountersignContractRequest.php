<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CountersignContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'countersigner_name' => ['required', 'string', 'max:255'],
            'agreement' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agreement.accepted' => 'Confirm you are authorized to execute this contract before counter-signing.',
            'countersigner_name.required' => 'Type the full legal name that will counter-sign the contract.',
        ];
    }
}
