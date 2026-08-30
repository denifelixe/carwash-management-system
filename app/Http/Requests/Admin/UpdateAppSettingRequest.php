<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class UpdateAppSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.master_app_settings.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'whatsapp' => ['required', 'string', 'regex:/^62[0-9]{8,13}$/'],
            'instagram' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9._]+$/'],
            'app_photo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
            'favicon' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('1mb')],
        ];
    }

    /**
     * @return array{app_name: string, whatsapp: string, instagram: string, app_photo: UploadedFile|null, favicon: UploadedFile|null}
     */
    public function branding(): array
    {
        return [
            'app_name' => $this->string('app_name')->toString(),
            'whatsapp' => $this->string('whatsapp')->toString(),
            'instagram' => $this->string('instagram')->toString(),
            'app_photo' => $this->file('app_photo'),
            'favicon' => $this->file('favicon'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $whatsapp = Str::of((string) $this->input('whatsapp'))
            ->replaceMatches('/\D+/', '')
            ->toString();

        if (Str::startsWith($whatsapp, '0')) {
            $whatsapp = '62'.Str::after($whatsapp, '0');
        } elseif (Str::startsWith($whatsapp, '8')) {
            $whatsapp = '62'.$whatsapp;
        }

        $this->merge([
            'whatsapp' => $whatsapp,
            'instagram' => Str::of((string) $this->input('instagram'))
                ->trim()
                ->ltrim('@')
                ->lower()
                ->toString(),
        ]);
    }
}
