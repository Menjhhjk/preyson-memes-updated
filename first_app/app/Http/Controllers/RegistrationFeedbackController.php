<?php

namespace App\Http\Controllers;

use App\Concerns\ProfileValidationRules;
use App\Rules\UniqueAccountField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegistrationFeedbackController extends Controller
{
    use ProfileValidationRules;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['username', 'email'])],
            'value' => ['required', 'string', 'max:255'],
        ]);
        $field = $data['field'];
        $value = Str::lower(trim($data['value']));
        $rules = $this->profileRules()[$field];
        $format = Validator::make([$field => $value], [
            $field => array_filter($rules, fn ($rule) => ! $rule instanceof UniqueAccountField),
        ]);
        $formatValid = $format->passes();
        $validator = $formatValid ? Validator::make([$field => $value], [$field => $rules]) : $format;

        return response()->json([
            'valid' => $validator->passes(),
            'format_valid' => $formatValid,
            'messages' => $validator->errors()->get($field),
        ])->header('Cache-Control', 'no-store');
    }
}
