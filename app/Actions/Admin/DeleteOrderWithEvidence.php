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

class DeleteOrderWithEvidence
{
    public function __construct(private DeleteOrder $deleteOrder) {}

    /** @param list<UploadedFile> $photos */
    public function handle(Order $order, Admin $admin, string $reason, array $photos): string
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($order, $admin, $reason, $photos, &$storedPaths): string {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                OperationalDataWindow::ensureOrderCanBeDeleted($order);

                $deletion = $order->deletions()->create([
                    'reason' => $reason,
                    'previous_status' => $order->status,
                    'deleted_by_admin_id' => $admin->id,
                    'deleted_by_name' => $admin->name,
                    'deleted_at' => now(),
                ]);

                foreach ($photos as $photo) {
                    try {
                        $path = $photo->store('order-deletions/'.$deletion->id, 'local');
                    } catch (Throwable $exception) {
                        report($exception);
                        throw ValidationException::withMessages(['photos' => 'Foto gagal disimpan. Silakan coba kembali.']);
                    }

                    if ($path === false) {
                        throw ValidationException::withMessages(['photos' => 'Foto gagal disimpan. Silakan coba kembali.']);
                    }

                    $storedPaths[] = $path;
                    $deletion->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $photo->getClientOriginalName(),
                        'size' => $photo->getSize(),
                    ]);
                }

                return $this->deleteOrder->handle($order, $admin);
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }
    }
}
