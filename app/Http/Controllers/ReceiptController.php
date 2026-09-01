<?php

namespace App\Http\Controllers;

use App\Models\OrderTransaction;
use App\Support\Admin\OrderPresenter;
use App\Support\Demo\Brand;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function __invoke(OrderTransaction $orderTransaction): Response
    {
        $orderTransaction->loadMissing([
            'recordedBy:id,name',
            'order.serviceVariations.service:id,name',
            'order.transactions.recordedBy:id,name',
        ]);

        return Inertia::render('receipts/Show', [
            'brand' => Brand::identity(),
            'receipt' => OrderPresenter::receipt($orderTransaction),
        ]);
    }
}
