<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class SignContractRequest extends FormRequest
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
            'signer_name' => ['required', 'string', 'max:255'],
            'agreement' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agreement.accepted' => 'You must confirm that you agree to the contract before signing.',
            'signer_name.required' => 'Type your full legal name to sign the contract.',
        ];
    }
}
