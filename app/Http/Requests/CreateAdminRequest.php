<?php

namespace App\Http\Requests;

use App\Models\SetupStatus;
use Illuminate\Foundation\Http\FormRequest;

class CreateAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow if setup is not completed
        return ! SetupStatus::isSetupCompleted();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'helpdesk_name' => ['required', 'string', 'max:255'],
            'helpdesk_url' => ['required', 'url', 'max:255'],
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s\-\'\.]+$/', // Simplified regex without Unicode
            ],
            'email' => [
                'required',
                'email',
                'unique:users,email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            'password_confirmation' => ['required'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'helpdesk_name.required' => 'Helpdesk name is required.',
            'helpdesk_url.required' => 'Helpdesk URL is required.',
            'helpdesk_url.url' => 'Please provide a valid URL for the helpdesk.',
            'name.required' => 'Administrator name is required.',
            'name.regex' => 'Administrator name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 12 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ];
    }

    public function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Setup has already been completed or admin user already exists.'
        );
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional security validation
            if ($this->input('password') && $this->input('name')) {
                // Check if password contains name (case insensitive)
                if (stripos($this->input('password'), $this->input('name')) !== false) {
                    $validator->errors()->add('password', 'Password cannot contain your name.');
                }
            }

            // Check if admin already exists
            if (SetupStatus::isCompleted('admin_created')) {
                $validator->errors()->add('email', 'Administrator has already been created.');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->email),
            'name' => ucwords(strtolower(trim($this->name))),
        ]);
    }
}
