<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFieldReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $cleanText = static function (mixed $value): mixed {
            if (! is_string($value)) {
                return $value;
            }

            $value = strip_tags($value);
            $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? $value;
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

            return trim($value);
        };

        $this->merge([
            'subject' => $cleanText($this->input('subject')),
            'message' => $cleanText($this->input('message')),
        ]);
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:8', 'max:120'],
            'message' => ['required', 'string', 'min:20', 'max:2000'],
            'station_id' => ['required', 'integer', 'exists:stations,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['subject' => 2, 'message' => 4] as $field => $minimumWords) {
                $value = (string) $this->input($field, '');
                preg_match_all('/[\p{L}\p{N}]+/u', $value, $matches);
                $words = $matches[0] ?? [];
                $distinctWords = array_unique(array_map('mb_strtolower', $words));

                if (count($words) < $minimumWords) {
                    $validator->errors()->add($field, $field === 'subject'
                        ? 'Please use a short descriptive phrase for the subject.'
                        : 'Please describe the observation in at least four words.');
                }

                if (count($distinctWords) < ($field === 'subject' ? 2 : 3)) {
                    $validator->errors()->add($field, 'Please include enough different words to describe the report clearly.');
                }

                if (preg_match('/([\p{L}\p{N}])\1{5,}/iu', $value)
                    || preg_match('/\b(test|testing|dummy|sample|asdf|qwerty|lorem ipsum)\b/iu', $value)) {
                    $validator->errors()->add($field, 'Please provide a genuine drainage observation rather than a test or repeated-character entry.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'subject.min' => 'The report subject must be at least 8 characters.',
            'message.min' => 'Please provide at least 20 characters describing the observation.',
            'station_id.exists' => 'Select a registered drainage station.',
        ];
    }
}
