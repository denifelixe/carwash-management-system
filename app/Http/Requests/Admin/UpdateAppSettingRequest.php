<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

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
            'meta_title' => ['required', 'string', 'max:70'],
            'meta_description' => ['required', 'string', 'max:200'],
            'remove_meta_image' => ['boolean'],
            'remove_app_photo' => ['boolean'],
            'meta_image' => [
                'nullable',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max('5mb'),
            ],
            'app_photo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
            'favicon' => [
                'nullable',
                File::types(['ico'])->max('512kb'),
            ],
            'favicon_16' => [
                'nullable',
                File::image()->types(['png'])->dimensions(Rule::dimensions()->width(16)->height(16))->max('256kb'),
            ],
            'favicon_32' => [
                'nullable',
                File::image()->types(['png'])->dimensions(Rule::dimensions()->width(32)->height(32))->max('256kb'),
            ],
            'apple_touch_icon' => [
                'nullable',
                File::image()->types(['png'])->dimensions(Rule::dimensions()->width(180)->height(180))->max('512kb'),
            ],
            'android_chrome_192' => [
                'nullable',
                File::image()->types(['png'])->dimensions(Rule::dimensions()->width(192)->height(192))->max('1mb'),
            ],
            'android_chrome_512' => [
                'nullable',
                File::image()->types(['png'])->dimensions(Rule::dimensions()->width(512)->height(512))->max('2mb'),
            ],
            'site_webmanifest' => ['nullable', File::types(['webmanifest', 'json'])->max('100kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.uploaded' => 'File gagal diunggah. Periksa ukuran file lalu coba lagi.',
            'meta_image.image' => 'Social image harus berupa gambar yang valid.',
            'meta_image.mimes' => 'Social image harus berformat PNG, JPG, JPEG, atau WebP.',
            'meta_image.max' => 'Ukuran social image maksimal 5 MB.',
            'app_photo.image' => 'Foto aplikasi harus berupa gambar yang valid.',
            'app_photo.mimes' => 'Foto aplikasi harus berformat PNG, JPG, JPEG, atau WebP.',
            'app_photo.max' => 'Ukuran foto aplikasi maksimal 2 MB.',
            'favicon.mimes' => 'Favicon utama harus berupa file ICO.',
            'favicon.max' => 'Ukuran favicon utama maksimal 512 KB.',
            'favicon_16.image' => 'Favicon 16x16 harus berupa gambar PNG yang valid.',
            'favicon_16.mimes' => 'Favicon 16x16 harus berformat PNG.',
            'favicon_16.dimensions' => 'Dimensi favicon 16x16 harus tepat 16x16 piksel.',
            'favicon_16.max' => 'Ukuran favicon 16x16 maksimal 256 KB.',
            'favicon_32.image' => 'Favicon 32x32 harus berupa gambar PNG yang valid.',
            'favicon_32.mimes' => 'Favicon 32x32 harus berformat PNG.',
            'favicon_32.dimensions' => 'Dimensi favicon 32x32 harus tepat 32x32 piksel.',
            'favicon_32.max' => 'Ukuran favicon 32x32 maksimal 256 KB.',
            'apple_touch_icon.image' => 'Apple touch icon harus berupa gambar PNG yang valid.',
            'apple_touch_icon.mimes' => 'Apple touch icon harus berformat PNG.',
            'apple_touch_icon.dimensions' => 'Dimensi apple touch icon harus tepat 180x180 piksel.',
            'apple_touch_icon.max' => 'Ukuran apple touch icon maksimal 512 KB.',
            'android_chrome_192.image' => 'Android Chrome 192 harus berupa gambar PNG yang valid.',
            'android_chrome_192.mimes' => 'Android Chrome 192 harus berformat PNG.',
            'android_chrome_192.dimensions' => 'Dimensi Android Chrome 192 harus tepat 192x192 piksel.',
            'android_chrome_192.max' => 'Ukuran Android Chrome 192 maksimal 1 MB.',
            'android_chrome_512.image' => 'Android Chrome 512 harus berupa gambar PNG yang valid.',
            'android_chrome_512.mimes' => 'Android Chrome 512 harus berformat PNG.',
            'android_chrome_512.dimensions' => 'Dimensi Android Chrome 512 harus tepat 512x512 piksel.',
            'android_chrome_512.max' => 'Ukuran Android Chrome 512 maksimal 2 MB.',
            'site_webmanifest.mimes' => 'Site webmanifest harus berupa file JSON atau WEBMANIFEST.',
            'site_webmanifest.max' => 'Ukuran site webmanifest maksimal 100 KB.',
        ];
    }

    /**
     * @return array{app_name: string, whatsapp: string, instagram: string, meta_title: string, meta_description: string, remove_meta_image: bool, remove_app_photo: bool, meta_image: UploadedFile|null, app_photo: UploadedFile|null, favicon: UploadedFile|null, favicon_16: UploadedFile|null, favicon_32: UploadedFile|null, apple_touch_icon: UploadedFile|null, android_chrome_192: UploadedFile|null, android_chrome_512: UploadedFile|null, site_webmanifest: UploadedFile|null}
     */
    public function branding(): array
    {
        return [
            'app_name' => $this->string('app_name')->toString(),
            'whatsapp' => $this->string('whatsapp')->toString(),
            'instagram' => $this->string('instagram')->toString(),
            'meta_title' => $this->string('meta_title')->toString(),
            'meta_description' => $this->string('meta_description')->toString(),
            'remove_meta_image' => $this->boolean('remove_meta_image'),
            'remove_app_photo' => $this->boolean('remove_app_photo'),
            'meta_image' => $this->file('meta_image'),
            'app_photo' => $this->file('app_photo'),
            'favicon' => $this->file('favicon'),
            'favicon_16' => $this->file('favicon_16'),
            'favicon_32' => $this->file('favicon_32'),
            'apple_touch_icon' => $this->file('apple_touch_icon'),
            'android_chrome_192' => $this->file('android_chrome_192'),
            'android_chrome_512' => $this->file('android_chrome_512'),
            'site_webmanifest' => $this->file('site_webmanifest'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $manifest = $this->file('site_webmanifest');

                if ($manifest instanceof UploadedFile && ! json_validate($manifest->getContent())) {
                    $validator->errors()->add(
                        'site_webmanifest',
                        'Site webmanifest harus berisi JSON yang valid.',
                    );
                }
            },
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
