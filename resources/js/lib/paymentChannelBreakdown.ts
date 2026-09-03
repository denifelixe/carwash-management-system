import type { CarwashTransactionChannel } from '@/types/demo';

export function paymentTenderedTotal(
    channels: CarwashTransactionChannel[],
): number {
    return channels.reduce(
        (total, channel) => total + Math.max(channel.amount, 0),
        0,
    );
}

export function paymentChange(
    channels: CarwashTransactionChannel[],
    bookedAmount: number,
): number {
    return Math.max(paymentTenderedTotal(channels) - bookedAmount, 0);
}

/** The revenue allocation after cash handed back to the customer is removed. */
export function financialPaymentBreakdown(
    channels: CarwashTransactionChannel[],
    bookedAmount: number,
): CarwashTransactionChannel[] {
    const financial = channels.map((channel) => ({
        ...channel,
        amount: Math.max(channel.amount, 0),
    }));
    let remainingChange = paymentChange(financial, bookedAmount);

    for (
        let index = financial.length - 1;
        index >= 0 && remainingChange > 0;
        index--
    ) {
        if (
            financial[index].label !== 'Tunai' &&
            !financial[index].label.startsWith('Tunai ')
        ) {
            continue;
        }

        const deduction = Math.min(financial[index].amount, remainingChange);
        financial[index].amount -= deduction;
        remainingChange -= deduction;
    }

    for (
        let index = financial.length - 1;
        index >= 0 && remainingChange > 0;
        index--
    ) {
        const deduction = Math.min(financial[index].amount, remainingChange);
        financial[index].amount -= deduction;
        remainingChange -= deduction;
    }

    return financial.filter((channel) => channel.amount > 0);
}
