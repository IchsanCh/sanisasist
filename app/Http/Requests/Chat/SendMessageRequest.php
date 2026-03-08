<?php

namespace App\Http\Requests\Chat;

use App\Models\ChatSession;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // $this->route('session') sudah di-resolve jadi ChatSession object
        // via route model binding — tidak perlu ::find() lagi
        $session = $this->route('session');

        if ($session instanceof ChatSession) {
            return $session->user_id === $this->user()->id;
        }

        // Fallback jika entah kenapa masih berupa ID
        $session = ChatSession::find($session);
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
