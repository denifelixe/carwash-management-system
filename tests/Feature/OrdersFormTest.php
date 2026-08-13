<?php

test('new orders defer crew assignment and payment to their dedicated workflows', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->not->toContain('v-model="draft.crew"')
        ->not->toContain('v-model="draft.paymentStatus"')
        ->not->toContain('Bayar nanti di kasir');
});
