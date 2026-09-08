<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Support\Admin\OperationalDataWindow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CancelOrder
{
    /** @param list<UploadedFile> $photos */
    public function handle(Order $order, Admin $admin, string $reason, array $photos): void
    {
        $storedPaths = [];

        try {
            DB::transaction(function () use ($order, $admin, $reason, $photos, &$storedPaths): void {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                OperationalDataWindow::ensureAllows($order->service_date);

                if (in_array($order->status, ['selesai', 'batal'], true)) {
                    throw ValidationException::withMessages(['status' => 'Order selesai atau yang sudah batal tidak dapat dibatalkan.']);
                }

                $cancellation = $order->cancellations()->create([
                    'reason' => $reason,
                    'previous_status' => $order->status,
                    'cancelled_by_admin_id' => $admin->id,
                    'cancelled_by_name' => $admin->name,
                    'cancelled_at' => now(),
                ]);

                foreach ($photos as $photo) {
                    try {
                        $path = $photo->store('order-cancellations/'.$cancellation->id, 'local');
                    } catch (Throwable $exception) {
                        report($exception);
                        throw ValidationException::withMessages(['photos' => 'Foto gagal disimpan. Silakan coba kembali.']);
                    }

                    if ($path === false) {
                        throw ValidationException::withMessages(['photos' => 'Foto gagal disimpan. Silakan coba kembali.']);
                    }

                    $storedPaths[] = $path;
                    $cancellation->photos()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $photo->getClientOriginalName(),
                        'size' => $photo->getSize(),
                    ]);
                }

                $order->update(['status' => 'batal']);
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }
    }
}
