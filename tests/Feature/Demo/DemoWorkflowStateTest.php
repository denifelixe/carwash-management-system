<?php

use App\Support\Demo\DateFilter;
use App\Support\Demo\Operations;
use App\Support\Demo\Reports;

test('demo workflow shares in-memory state across admin modules', function () {
    $store = file_get_contents(
        resource_path('js/composables/useCarwashWorkflow.ts'),
    );
    $orders = file_get_contents(
        resource_path('js/pages/demo/admin/Orders.vue'),
    );
    $pos = file_get_contents(
        resource_path('js/pages/demo/admin/Pos.vue'),
    );
    $finance = file_get_contents(
        resource_path('js/pages/demo/admin/Finance.vue'),
    );

    expect($store)
        ->toContain('const orders = ref<CarwashOrder[]>([])')
        ->toContain('const moneyIn = ref<CarwashMoneyEntry[]>([])')
        ->not->toContain('localStorage')
        ->not->toContain('sessionStorage')
        ->and($orders)
        ->toContain('workflow.hydrateOrders(props.orders)')
        ->toContain('workflow.addOrder({')
        ->and($pos)
        ->toContain('const orderList = workflow.orders')
        ->toContain('workflow.addMoneyIn({')
        ->and($finance)
        ->toContain('const incomeList = workflow.moneyIn')
        ->toContain('const orderList = workflow.orders');
});

test('payments are attributed to the active user and their configured shift', function () {
    $pos = file_get_contents(
        resource_path('js/pages/demo/admin/Pos.vue'),
    );
    $finance = file_get_contents(
        resource_path('js/pages/demo/admin/Finance.vue'),
    );

    expect($pos)
        ->toContain('recordedBy: props.persona.name')
        ->toContain('shift: props.persona.shift')
        ->and($finance)
        ->toContain(".toLocaleLowerCase('id-ID')")
        ->toContain('.includes(activeShift.value)')
        ->toContain('recordedBy: props.persona.name')
        ->toContain('shift: props.persona.shift')
        ->toContain('{{ entry.shift }}');
});

test('order summary ignores bookings from other dates loaded by another module', function () {
    $orders = file_get_contents(
        resource_path('js/pages/demo/admin/Orders.vue'),
    );

    expect($orders)
        ->toContain('const scopedOrders = computed<CarwashOrder[]>(() =>')
        ->toContain("props.filters.date === '' || order.date === props.filters.date")
        ->toContain('return scopedOrders.value.filter((order) => {')
        ->toContain('scopedOrders.value.filter((order) =>')
        ->toContain('`${scopedOrders.value.length} order')
        ->toContain(':value="String(scopedOrders.length)"');
});

/*
 * The POS hydrates the store from the day's orders and the booking queue at
 * once, and a booking scheduled for that day sits in both lists. Banking each
 * id only against what the store already held let the repeat through, so the
 * booking rendered twice and the panel counted 9 where the data holds 6.
 */
test('hydrating from overlapping lists stores each record once', function () {
    $store = file_get_contents(
        resource_path('js/composables/useCarwashWorkflow.ts'),
    );

    expect($store)
        ->toContain('const knownIds = new Set(target.map(identity));')
        // The id has to be banked as it is taken, not just read up front.
        ->toContain('knownIds.add(id);')
        ->and(mb_strpos($store, 'knownIds.add(id);'))
        ->toBeLessThan(mb_strpos($store, 'target.push(clone(item));'));

    expect(file_get_contents(resource_path('js/pages/demo/admin/Pos.vue')))
        ->toContain('workflow.hydrateOrders([...props.dailyOrders, ...props.partialPaymentBookings]);');
});

test('the POS booking queue holds only the bookings the server sent', function () {
    $bookings = Operations::partialPaymentBookingOrders();
    $daily = DateFilter::apply(
        Operations::orders(),
        Reports::todayDate(),
    );

    $bookingIds = array_column($bookings, 'id');
    $overlap = array_intersect(array_column($daily, 'id'), $bookingIds);

    // The overlap is expected; what must not happen is it being stored twice.
    expect($overlap)->not->toBeEmpty()
        ->and($bookingIds)->toBe(array_unique($bookingIds));
});
