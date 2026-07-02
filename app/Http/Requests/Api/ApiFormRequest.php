<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Every Api/*Request extends this so validation and authorization failures
 * render the same JSON envelope as every other /api/v1/* response, instead
 * of Laravel's default redirect-back-with-errors (web) behavior.
 */
abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The given data was invalid.',
            'data' => ['errors' => $validator->errors()],
        ], 422));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'This action is unauthorized.',
            'data' => null,
        ], 403));
    }
}
