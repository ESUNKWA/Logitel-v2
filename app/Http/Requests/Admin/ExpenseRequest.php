<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExpenseSource;
use App\Models\ExpenseCategory;
use App\Support\BusinessDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A new expense (V2). The receipt is mandatory (client decision): a real image (JPG, PNG, WebP —
 * decoded to check it) or a real PDF (magic bytes), ≤ 2 Mo (PHP's limit here; phones' photos are
 * downsized in the browser first). Amount, date and category are re-checked server side.
 */
class ExpenseRequest extends FormRequest
{
    /** Server-detected types accepted for the receipt => stored extension. */
    public const RECEIPT_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = BusinessDay::today();
        // A receptionist may record an expense up to 31 days back, a validator up to a year.
        $oldest = $today->copy()->subDays($this->user()->can('expenses.validate') ? 366 : 31);

        return [
            'business_date' => ['required', 'string', 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString(), 'after_or_equal:'.$oldest->toDateString()],
            'expense_category_id' => ['required', 'integer', Rule::exists(ExpenseCategory::class, 'id')->where('is_active', true)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'source' => ['required', 'string', Rule::enum(ExpenseSource::class)],
            'beneficiary' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'receipt' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $file = $this->file('receipt');
                if (! $file instanceof UploadedFile || $validator->errors()->has('receipt')) {
                    return;
                }
                if (! $this->receiptMime()) {
                    $validator->errors()->add('receipt', 'Le justificatif doit être une photo (JPG, PNG, WebP) ou un PDF.');
                }
            },
        ];
    }

    /**
     * The receipt's real type (detected from its content, never from the browser), or null when
     * the file isn't a genuine image / PDF.
     */
    public function receiptMime(): ?string
    {
        $file = $this->file('receipt');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return null;
        }
        $mime = (string) $file->getMimeType();
        if (! array_key_exists($mime, self::RECEIPT_TYPES)) {
            return null;
        }
        if ($mime === 'application/pdf') {
            $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 5);

            return $head === '%PDF-' ? $mime : null;
        }
        $size = @getimagesize($file->getRealPath());

        return $size && $size[0] >= 50 && $size[1] >= 50 && $size[0] <= 8000 && $size[1] <= 8000 ? $mime : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt.required' => 'Le justificatif est obligatoire : prenez le reçu en photo ou joignez un PDF.',
            'receipt.max' => 'Le justificatif ne doit pas dépasser 2 Mo.',
            'receipt.mimes' => 'Le justificatif doit être une photo (JPG, PNG, WebP) ou un PDF.',
            'business_date.before_or_equal' => 'La date ne peut pas être dans le futur.',
            'business_date.after_or_equal' => 'Cette date est trop ancienne.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'business_date' => 'date',
            'expense_category_id' => 'catégorie',
            'amount' => 'montant',
            'source' => 'source du paiement',
            'beneficiary' => 'bénéficiaire',
            'description' => 'description',
            'receipt' => 'justificatif',
        ];
    }
}
