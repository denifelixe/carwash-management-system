<?php

namespace App\Http\Requests\Admin;

use App\Support\AppSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class UpdateReceiptSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.master_receipt.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receipt_business_name' => ['required', 'string', 'max:60'],
            'receipt_footer_note' => ['nullable', 'string', 'max:120'],
            'receipt_show_logo' => ['boolean'],
            'receipt_show_qr' => ['boolean'],
            'receipt_logo_width' => [
                'nullable',
                'integer',
                'between:'.AppSettings::RECEIPT_LOGO_WIDTH_MIN.','.AppSettings::RECEIPT_LOGO_WIDTH_MAX,
            ],
            'remove_receipt_photo' => ['boolean'],
            'receipt_photo' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_business_name.required' => 'Nama bisnis pada struk wajib diisi.',
            'receipt_business_name.max' => 'Nama bisnis pada struk maksimal 60 karakter, agar muat pada roll 80mm.',
            'receipt_footer_note.max' => 'Catatan kaki struk maksimal 120 karakter.',
            'receipt_photo.image' => 'Logo struk harus berupa gambar yang valid.',
            'receipt_photo.mimes' => 'Logo struk harus berformat PNG, JPG, JPEG, atau WebP.',
            'receipt_photo.max' => 'Ukuran logo struk maksimal 2 MB.',
            'receipt_logo_width.between' => 'Lebar logo struk harus antara '.AppSettings::RECEIPT_LOGO_WIDTH_MIN.' dan '.AppSettings::RECEIPT_LOGO_WIDTH_MAX.' mm.',
        ];
    }

    /**
     * @return array{receipt_business_name: string, receipt_footer_note: string, receipt_show_logo: bool, receipt_show_qr: bool, receipt_logo_width: int, remove_receipt_photo: bool, receipt_photo: UploadedFile|null}
     */
    public function receipt(): array
    {
        return [
            'receipt_business_name' => $this->string('receipt_business_name')->toString(),
            'receipt_footer_note' => $this->string('receipt_footer_note')->toString(),
            'receipt_show_logo' => $this->boolean('receipt_show_logo'),
            'receipt_show_qr' => $this->boolean('receipt_show_qr'),
            /* An absent size leaves the printed mark exactly as it is. */
            'receipt_logo_width' => $this->filled('receipt_logo_width')
                ? $this->integer('receipt_logo_width')
                : AppSettings::receiptLogoWidth(),
            'remove_receipt_photo' => $this->boolean('remove_receipt_photo'),
            'receipt_photo' => $this->file('receipt_photo'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'receipt_business_name' => Str::of((string) $this->input('receipt_business_name'))->squish()->toString(),
            'receipt_footer_note' => Str::of((string) $this->input('receipt_footer_note'))->squish()->toString(),
        ]);
    }
}
