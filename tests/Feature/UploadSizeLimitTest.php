<?php

use App\Http\Requests\Admin\StoreCashEntryRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Requests\Admin\UpdateAppSettingRequest;
use App\Http\Requests\Admin\UpdateCashEntryRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Requests\Admin\UpdateReceiptSettingRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Admin;
use App\Models\CashEntry;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;

test('uploads accept twenty megabytes and reject files above the limit', function (string $requestClass, string $field, int $dimension) {
    $request = new $requestClass;
    $request->setUserResolver(fn () => Admin::factory()->make(['id' => 1, 'is_owner' => true]));
    $route = new Route(['POST'], '/', fn () => null);
    $route->bind(Request::create('/'));
    $route->setParameter('cashEntry', CashEntry::factory()->make(['id' => 1, 'direction' => 'out']));
    $request->setRouteResolver(fn () => $route);
    $rules = $request->rules()[$field];

    foreach ([20480 => true, 20481 => false] as $size => $accepted) {
        $file = match ($field) {
            'favicon' => UploadedFile::fake()->createWithContent('favicon.ico', file_get_contents(public_path('favicon.ico'))),
            'site_webmanifest' => UploadedFile::fake()->createWithContent('site.webmanifest', '{"name":"Carwash"}'),
            default => UploadedFile::fake()->image('photo.png', $dimension, $dimension),
        };
        $file->size($size);
        $data = str_ends_with($field, '.*')
            ? [str_replace('.*', '', $field) => [$file]]
            : [$field => $file];
        $validator = Validator::make($data, [$field => $rules]);

        expect($validator->passes())->toBe($accepted, $validator->errors()->toJson());
    }
})->with([
    'new finance attachment' => [StoreCashEntryRequest::class, 'attachments.*', 20],
    'edited finance attachment' => [UpdateCashEntryRequest::class, 'attachments.*', 20],
    'cancellation photo' => [UpdateOrderStatusRequest::class, 'photos.*', 20],
    'receipt logo' => [UpdateReceiptSettingRequest::class, 'receipt_photo', 20],
    'social image' => [UpdateAppSettingRequest::class, 'meta_image', 20],
    'app photo' => [UpdateAppSettingRequest::class, 'app_photo', 20],
    'favicon' => [UpdateAppSettingRequest::class, 'favicon', 20],
    'favicon 16' => [UpdateAppSettingRequest::class, 'favicon_16', 16],
    'favicon 32' => [UpdateAppSettingRequest::class, 'favicon_32', 32],
    'apple icon' => [UpdateAppSettingRequest::class, 'apple_touch_icon', 180],
    'android 192' => [UpdateAppSettingRequest::class, 'android_chrome_192', 192],
    'android 512' => [UpdateAppSettingRequest::class, 'android_chrome_512', 512],
    'webmanifest' => [UpdateAppSettingRequest::class, 'site_webmanifest', 20],
    'profile photo' => [ProfileUpdateRequest::class, 'photo', 20],
    'admin photo' => [UpdateAdminUserRequest::class, 'photo', 20],
]);
