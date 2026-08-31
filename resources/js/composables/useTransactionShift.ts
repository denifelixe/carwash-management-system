import type {
    CarwashTransactionShiftAssignment,
    CarwashTransactionShiftOption,
} from '@/types/demo';

function clockMinutes(time: string): number {
    const [hour, minute] = time.split(':').map(Number);

    return hour * 60 + minute;
}

function currentMinutes(timezone: string, at: Date = new Date()): number {
    const parts = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: timezone,
    }).formatToParts(at);
    const hour = Number(parts.find((part) => part.type === 'hour')?.value ?? 0);
    const minute = Number(
        parts.find((part) => part.type === 'minute')?.value ?? 0,
    );

    return hour * 60 + minute;
}

function containsMinute(
    shift: CarwashTransactionShiftOption,
    minute: number,
): boolean {
    const startsAt = clockMinutes(shift.starts_at);
    const endsAt = clockMinutes(shift.ends_at);

    if (startsAt === endsAt) {
        return false;
    }

    return startsAt < endsAt
        ? minute >= startsAt && minute < endsAt
        : minute >= startsAt || minute < endsAt;
}

export function matchingTransactionShifts(
    assignment: CarwashTransactionShiftAssignment,
    timezone: string,
    at: Date = new Date(),
): CarwashTransactionShiftOption[] {
    if (assignment.mode !== 'schedule') {
        return [];
    }

    const minute = currentMinutes(timezone, at);

    return assignment.shifts.filter((shift) => containsMinute(shift, minute));
}
