import { ref } from 'vue';
import type {
    CarwashCustomer,
    CarwashMoneyEntry,
    CarwashOrder,
} from '@/types/demo';

/**
 * Demo-only workflow state. Module state survives Inertia page swaps, while a
 * full browser reload evaluates this file again and restores the server data.
 */
const orders = ref<CarwashOrder[]>([]);
const customers = ref<CarwashCustomer[]>([]);
const moneyIn = ref<CarwashMoneyEntry[]>([]);
const moneyOut = ref<CarwashMoneyEntry[]>([]);

function cloneOrder(order: CarwashOrder): CarwashOrder {
    return {
        ...order,
        serviceIds: [...order.serviceIds],
        serviceItems: order.serviceItems.map((item) => ({
            ...item,
            variations: item.variations ? { ...item.variations } : null,
        })),
        transactions: order.transactions.map((transaction) => ({
            ...transaction,
            channelBreakdown: transaction.channelBreakdown.map((channel) => ({
                ...channel,
            })),
            tenderBreakdown: transaction.tenderBreakdown.map((channel) => ({
                ...channel,
            })),
        })),
    };
}

function cloneCustomer(customer: CarwashCustomer): CarwashCustomer {
    return {
        ...customer,
        vehicles: customer.vehicles.map((vehicle) => ({ ...vehicle })),
    };
}

function cloneMoneyEntry(entry: CarwashMoneyEntry): CarwashMoneyEntry {
    return {
        ...entry,
        channelBreakdown: entry.channelBreakdown.map((channel) => ({
            ...channel,
        })),
        attachments: entry.attachments?.map((attachment) => ({
            ...attachment,
        })),
    };
}

/**
 * Adds whatever the store has not seen yet.
 *
 * A page may hydrate from several lists that overlap — the POS passes the day's
 * orders alongside the booking queue, and a booking scheduled for that day sits
 * in both — so every id has to be banked as it is taken, not just the ones the
 * store already held. Snapshotting the set up front lets a repeat inside
 * `source` through and the record renders twice.
 */
function mergeMissing<T>(
    target: T[],
    source: T[],
    identity: (item: T) => number | string,
    clone: (item: T) => T,
): void {
    const knownIds = new Set(target.map(identity));

    for (const item of source) {
        const id = identity(item);

        if (knownIds.has(id)) {
            continue;
        }

        knownIds.add(id);
        target.push(clone(item));
    }
}

export function useCarwashWorkflow() {
    function hydrateOrders(initialOrders: CarwashOrder[]): void {
        mergeMissing(
            orders.value,
            initialOrders,
            (order) => order.id,
            cloneOrder,
        );
    }

    function hydrateCustomers(initialCustomers: CarwashCustomer[]): void {
        mergeMissing(
            customers.value,
            initialCustomers,
            (customer) => customer.id,
            cloneCustomer,
        );
    }

    function hydrateMoneyIn(initialEntries: CarwashMoneyEntry[]): void {
        mergeMissing(
            moneyIn.value,
            initialEntries,
            (entry) => entry.id,
            cloneMoneyEntry,
        );
    }

    function hydrateMoneyOut(initialEntries: CarwashMoneyEntry[]): void {
        mergeMissing(
            moneyOut.value,
            initialEntries,
            (entry) => entry.id,
            cloneMoneyEntry,
        );
    }

    function addOrder(order: CarwashOrder): void {
        orders.value.unshift(order);
    }

    function addCustomer(customer: CarwashCustomer): void {
        if (
            !customers.value.some((candidate) => candidate.id === customer.id)
        ) {
            customers.value.unshift(customer);
        }
    }

    function addMoneyIn(entry: CarwashMoneyEntry): void {
        if (!moneyIn.value.some((candidate) => candidate.id === entry.id)) {
            moneyIn.value.unshift(entry);
        }
    }

    function addMoneyOut(entry: CarwashMoneyEntry): void {
        if (!moneyOut.value.some((candidate) => candidate.id === entry.id)) {
            moneyOut.value.unshift(entry);
        }
    }

    return {
        orders,
        customers,
        moneyIn,
        moneyOut,
        hydrateOrders,
        hydrateCustomers,
        hydrateMoneyIn,
        hydrateMoneyOut,
        addOrder,
        addCustomer,
        addMoneyIn,
        addMoneyOut,
    };
}
