<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** «Mi cuenta → Direcciones»: las mismas reglas que la dirección de «Finalizar compra». */
class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return CheckoutRequest::addressRules(strtoupper((string) $this->input('country', 'ES')));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre y apellidos', 'phone' => 'teléfono', 'address_line1' => 'dirección', 'postal_code' => 'código postal', 'city' => 'población', 'country' => 'país'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Escribe un teléfono válido (solo números, con prefijo si es de fuera de España).',
            'postal_code.regex' => 'El código postal no tiene el formato de ese país.',
        ];
    }

    /**
     * Datos listos para guardar.
     *
     * @return array<string, ?string>
     */
    public function address(): array
    {
        $v = $this->validated();
        $clean = fn (string $k): ?string => isset($v[$k]) && trim((string) $v[$k]) !== '' ? trim((string) $v[$k]) : null;

        return [
            'name' => (string) $clean('name'),
            'phone' => preg_replace('/\s+/', '', (string) $clean('phone')),
            'address_line1' => (string) $clean('address_line1'),
            'address_line2' => $clean('address_line2'),
            'postal_code' => strtoupper((string) $clean('postal_code')),
            'city' => (string) $clean('city'),
            'province' => $clean('province'),
            'country' => strtoupper((string) $clean('country')),
        ];
    }
}
