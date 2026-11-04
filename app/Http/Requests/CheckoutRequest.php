<?php

namespace App\Http\Requests;

use App\Modules\Orders\Contracts\CheckoutDetails;
use App\Storefront\Checkout\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $country = strtoupper((string) $this->input('country', 'ES'));

        return [
            'email' => ['required', 'email:rfc', 'max:190'],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{9,20}$/'],
            'address_line1' => ['required', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'postal_code' => ['required', 'string', 'regex:'.Countries::postalPattern($country)],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['required', Rule::in(array_keys(Countries::ALL))],
            'note' => ['nullable', 'string', 'max:500'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email', 'name' => 'nombre y apellidos', 'phone' => 'teléfono', 'address_line1' => 'dirección',
            'postal_code' => 'código postal', 'city' => 'población', 'country' => 'país', 'terms' => 'condiciones de venta',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Escribe un teléfono válido (solo números, con prefijo si es de fuera de España).',
            'postal_code.regex' => 'El código postal no tiene el formato de ese país.',
            'terms.accepted' => 'Para comprar tienes que aceptar las condiciones de venta y la política de privacidad.',
        ];
    }

    public function details(): CheckoutDetails
    {
        $v = $this->validated();

        return new CheckoutDetails(
            email: (string) $v['email'],
            name: trim((string) $v['name']),
            phone: preg_replace('/\s+/', '', (string) $v['phone']) ?? (string) $v['phone'],
            addressLine1: trim((string) $v['address_line1']),
            addressLine2: isset($v['address_line2']) ? trim((string) $v['address_line2']) : null,
            postalCode: strtoupper(trim((string) $v['postal_code'])),
            city: trim((string) $v['city']),
            province: isset($v['province']) ? trim((string) $v['province']) : null,
            country: strtoupper((string) $v['country']),
            note: isset($v['note']) ? trim((string) $v['note']) : null,
            termsVersion: (string) config('clavedealma.shop.terms_version'),
        );
    }
}
