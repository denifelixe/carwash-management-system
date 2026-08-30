<?php

namespace App\Http\Requests\Admin;

use App\Support\AppSettings;
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
            'meta_image' => [
                'nullable',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->dimensions(Rule::dimensions()->width(1200)->height(630))
                    ->max('5mb'),
            ],
            'app_photo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
            'favicon' => [
                Rule::requiredIf(AppSettings::get(AppSettings::FAVICON) === null),
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
     * @return array{app_name: string, whatsapp: string, instagram: string, meta_title: string, meta_description: string, meta_image: UploadedFile|null, app_photo: UploadedFile|null, favicon: UploadedFile|null, favicon_16: UploadedFile|null, favicon_32: UploadedFile|null, apple_touch_icon: UploadedFile|null, android_chrome_192: UploadedFile|null, android_chrome_512: UploadedFile|null, site_webmanifest: UploadedFile|null}
     */
    public function branding(): array
    {
        return [
            'app_name' => $this->string('app_name')->toString(),
            'whatsapp' => $this->string('whatsapp')->toString(),
            'instagram' => $this->string('instagram')->toString(),
            'meta_title' => $this->string('meta_title')->toString(),
            'meta_description' => $this->string('meta_description')->toString(),
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
