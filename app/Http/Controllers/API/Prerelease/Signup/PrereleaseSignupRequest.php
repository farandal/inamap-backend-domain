<?php

namespace Domain\App\Http\Controllers\API\Prerelease\Signup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrereleaseSignupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name'               => 'required|string|max:255',
            'email'              => [
                'required',
                'email',
                'max:255',
                Rule::unique('prerelease_signups', 'email')->ignore($this->route('id')),
            ],
            'contact_phone'      => 'nullable|string|max:50',
            'business_website'   => 'nullable|string|max:255',
            'business_instagram' => 'nullable|string|max:255',
            'preferred_language' => 'nullable|string|in:en,es',
        ];
    }

    /**
     * Get the validated data from the request.
     *
     * @param string|null $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validatedData = parent::validated($key, $default);
        return data_get($validatedData, $key, $default);
    }
}
