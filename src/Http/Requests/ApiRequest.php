<?php

namespace TecClub\ReleaseKit\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

/**
 * Validation failures always answer with a JSON 422, whatever the Accept
 * header: app clients and download managers rarely send one.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => (new ValidationException($validator))->getMessage(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    public function channel(): ?string
    {
        return $this->validated('channel');
    }

    public function platform(): ?string
    {
        return $this->validated('platform');
    }

    public function architecture(): ?string
    {
        return $this->validated('architecture');
    }
}
