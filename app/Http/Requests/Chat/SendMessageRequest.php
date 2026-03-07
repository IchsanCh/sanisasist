<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pastikan session milik user yang login
        $session = \App\Models\ChatSession::find($this->route('session'));

        return $session && $session->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Pesan tidak boleh kosong.',
            'message.max'      => 'Pesan terlalu panjang (maksimum 10.000 karakter).',
        ];
    }
}
