<?php

test('demo workflow shares in-memory state across admin modules', function () {
    $store = file_get_contents(
        resource_path('js/composables/useCarwashWorkflow.ts'),
    );
    $orders = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );
    $pos = file_get_contents(
        resource_path('js/pages/carwash/admin/Pos.vue'),
    );
    $finance = file_get_contents(
        resource_path('js/pages/carwash/admin/Finance.vue'),
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
        resource_path('js/pages/carwash/admin/Pos.vue'),
    );
    $finance = file_get_contents(
        resource_path('js/pages/carwash/admin/Finance.vue'),
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
        resource_path('js/pages/carwash/admin/Orders.vue'),
    );

    expect($orders)
        ->toContain('const scopedOrders = computed<CarwashOrder[]>(() =>')
        ->toContain("props.filters.date === '' || order.date === props.filters.date")
        ->toContain('return scopedOrders.value.filter((order) => {')
        ->toContain('scopedOrders.value.filter((order) =>')
        ->toContain('`${scopedOrders.value.length} order')
        ->toContain(':value="String(scopedOrders.length)"');
});
