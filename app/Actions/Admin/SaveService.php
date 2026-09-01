<?php

namespace App\Actions\Admin;

use App\Models\Service;
use App\Models\ServiceVariation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveService
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, ?Service $service = null): Service
    {
        return DB::transaction(function () use ($data, $service): Service {
            $variationRows = Arr::pull($data, 'service_variations');

            if ($service === null) {
                $data['sort_order'] = (int) Service::query()->max('sort_order') + 1;
                $service = Service::query()->create($data);
            } else {
                $service->update($data);
            }

            $retainedIds = [];

            foreach ($variationRows as $variationData) {
                $variationId = Arr::pull($variationData, 'id');

                if ($variationId === null) {
                    $variation = $service->serviceVariations()->create($variationData);
                } else {
                    $variation = $service->serviceVariations()->whereKey($variationId)->firstOrFail();
                    $variation->update($variationData);
                }

                $retainedIds[] = $variation->id;
            }

            $service->serviceVariations()->whereNotIn('id', $retainedIds)->get()
                ->each(function (ServiceVariation $variation): void {
                    if ($variation->orders()->exists()) {
                        $variation->update(['is_active' => false]);

                        return;
                    }

                    $variation->delete();
                });

            return $service->load('serviceVariations');
        });
    }
}
