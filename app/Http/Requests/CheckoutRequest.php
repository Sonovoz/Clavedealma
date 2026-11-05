<?php

namespace App\Http\Requests;

use App\Modules\Orders\Contracts\CheckoutDetails;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Platform\Legal\LegalDocument;
use App\Platform\Legal\LegalPage;
use App\Storefront\Checkout\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
        $rules = self::addressRules(strtoupper((string) $this->input('country', 'ES'))) + [
            'email' => ['required', 'email:rfc', 'max:190'],
            'note' => ['nullable', 'string', 'max:500'],
            'terms' => ['accepted'],
        ];

        // «¿Crear una cuenta?» (como WooCommerce): solo para quien compra sin sesión.
        if ($this->user() === null && $this->boolean('create_account')) {
            $rules['email'][] = 'unique:users,email';
            $rules['account_password'] = ['required', 'string', Password::min(8)];
        }

        // «Métodos de pago» (Redsys: tarjeta o Bizum), como en WooCommerce.
        $methods = app(PaymentGateway::class)->methods();
        if ($methods !== []) {
            $rules['payment_method'] = ['required', Rule::in(array_keys($methods))];
        }

        return $rules;
    }

    /**
     * Reglas de la dirección; también las usa «Mi cuenta → Direcciones».
     *
     * @return array<string, list<mixed>>
     */
    public static function addressRules(string $country): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{9,20}$/'],
            'address_line1' => ['required', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'postal_code' => ['required', 'string', 'regex:'.Countries::postalPattern($country)],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['required', Rule::in(array_keys(Countries::ALL))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email', 'name' => 'nombre y apellidos', 'phone' => 'teléfono', 'address_line1' => 'dirección',
            'postal_code' => 'código postal', 'city' => 'población', 'country' => 'país', 'terms' => 'condiciones de venta', 'account_password' => 'contraseña de la cuenta',
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
            'payment_method.*' => 'Elige un método de pago.',
            'terms.accepted' => 'Para comprar tienes que aceptar las condiciones de venta y la política de privacidad.',
            'email.unique' => 'Ya hay una cuenta registrada con tu dirección de correo electrónico. Accede para continuar.',
        ];
    }

    public function details(?int $customerId = null): CheckoutDetails
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
            // Versión de las condiciones de venta que acepta el cliente (se guarda en el pedido).
            termsVersion: LegalDocument::current(LegalPage::Terms, null)?->label() ?? (string) config('clavedealma.shop.terms_version'),
            customerId: $customerId,
        );
    }
}
