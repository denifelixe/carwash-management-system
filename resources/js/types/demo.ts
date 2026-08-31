/**
 * Shapes mirroring the PHP demo-data providers in `app/Support/Carwash`.
 */

export interface CarwashBrand {
    name: string;
    system: string;
    logo: string;
    photo: string | null;
    whatsapp: string;
    instagram: string;
    stampTarget: number;
    stampReward: string;
    today: string;
}

export interface CarwashShift {
    id: string;
    name: string;
    time: string | null;
    cashier: string;
    initials: string;
    revenue: number;
    transactions: number;
    vehiclesServed: number;
    moneyIn: number;
    moneyOut: number;
    status: string;
}

export interface CarwashNotification {
    id: number;
    title: string;
    message: string;
    time: string;
    icon: string;
    type: string;
    unread: boolean;
}

export interface CarwashPromo {
    id: number;
    title: string;
    description: string;
    badge: string;
    gradFrom: string;
    gradTo: string;
    icon: string;
}

export interface CarwashService {
    id: number;
    name: string;
    category: string;
    price: number;
    stamps: number;
    icon: string;
    description: string;
    popular: boolean;
    isActive: boolean;
    serviceGroup?: {
        id: number;
        name: string;
    } | null;
}

export interface CarwashReward {
    id: number;
    name: string;
    description: string;
    requiredStamps: number;
    applicableServiceIds: number[];
    icon: string;
    category: string;
    status: string;
    stock: number;
    redeemed: number;
}

export interface CarwashCustomer {
    id: number;
    name: string;
    memberId: string;
    phone: string;
    email: string;
    vehicle: string;
    plate: string;
    vehicles: CarwashVehicle[];
    stamps: number;
    lifetimeStamps: number;
    visits: number;
    spend: number;
    joinedAt: string;
    lastVisit: string;
    initials: string;
    status: string;
    hasAccount: boolean;
}

export interface CarwashPaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface CarwashPaginated<T> {
    data: T[];
    meta: CarwashPaginationMeta;
}

export interface CarwashMemberFilters {
    q: string;
    status: string;
    account: string;
    page: number;
}

export interface CarwashMemberStats {
    total: number;
    active: number;
    withAccount: number;
    circulatingStamps: number;
}

export interface CarwashMemberDetail {
    customer: CarwashCustomer;
    orders: CarwashOrder[];
    stampHistory: CarwashStampEntry[];
}

export interface CarwashVehicle {
    id?: number;
    name: string;
    plate: string;
    type: string;
    isPrimary: boolean;
}

export interface CarwashMember {
    id: number;
    name: string;
    memberId: string;
    phone: string;
    email: string;
    stamps: number;
    lifetimeStamps: number;
    visits: number;
    spend: number;
    joinedAt: string;
    initials: string;
    referralCode: string;
    rewardsClaimed: number;
    vehicles: CarwashVehicle[];
}

export interface CarwashStampEntry {
    id: number;
    title: string;
    detail: string;
    stamps: number;
    type: string;
    date: string;
    icon: string;
}

export interface CarwashWashEntry {
    id: number;
    service: string;
    vehicle: string;
    date: string;
    total: number;
    stamps: number;
    rating: number;
    status: string;
}

export interface CarwashVoucher {
    id: number;
    name: string;
    code: string;
    expiresAt: string;
    icon: string;
    status: string;
}

/**
 * Derived from how much of the order has been collected: nothing yet, a partial
 * payment, or the full amount.
 */
export type CarwashPaymentStatus = 'belum bayar' | 'sebagian' | 'lunas';

export interface CarwashTransaction {
    id: string;
    orderId: number;
    /** ISO day the payment was received. */
    date: string;
    time: string;
    type: 'Pembayaran Sebagian' | 'Pembayaran Lunas';
    amount: number;
    channels: string;
    channelBreakdown: CarwashTransactionChannel[];
    /** User and shift that accepted the payment in this browser session. */
    recordedBy?: string | null;
    shift?: string | null;
}

export interface CarwashWorkShift {
    /** How a tab addresses the shift; a payment is stamped with `name`. */
    key: string;
    name: string;
    /** The window it covers, as '08.00 - 16.00'; null when it has no hours. */
    time: string | null;
}

export interface CarwashTransactionChannel {
    label: string;
    amount: number;
    reference?: string;
}

export interface CarwashOrder {
    id: number;
    orderNo: string;
    invoice: string;
    /** ISO day the order belongs to. */
    date: string;
    time: string;
    /** ISO day the booking was first recorded; null for walk-in orders. */
    bookingDate: string | null;
    customerId: number | null;
    customer: string;
    phone: string;
    vehicle: string;
    plate: string;
    items: string;
    serviceIds: number[];
    /** Current bill total after any discount applied by the cashier. */
    total: number;
    discount: number;
    /** Name of the reward redeemed by the cashier, or `'—'`. */
    reward: string;
    paidAmount: number;
    payment: string;
    paymentStatus: CarwashPaymentStatus;
    status: string;
    stampsEarned: number;
    crew: string;
    bay: string;
    source: string;
    transactions: CarwashTransaction[];
}

export interface CarwashQueueItem {
    id: number;
    plate: string;
    vehicle: string;
    owner: string;
    service: string;
    crew: string;
    bay: string;
    status: string;
    progress: number;
    eta: string;
}

export interface CarwashBooking {
    id: number;
    code: string;
    customerId: number | null;
    customer: string;
    phone: string;
    vehicle: string;
    plate: string;
    service: string;
    serviceIds: number[];
    /** ISO date the customer is expected. */
    date: string;
    /** ISO date the booking was first recorded. */
    bookingDate: string;
    /** Where the job stands, read back from the order module. */
    orderStatus: string;
    estimate: number;
    notes: string;
}

export interface CarwashCrewMember {
    name: string;
    role: string;
    jobs: number;
    rating: number;
    initials: string;
}

export interface CarwashMoneyEntry {
    id: number | string;
    /** Database key for a live POS payment that may be corrected in Finance. */
    transactionId?: number | null;
    ref: string;
    date: string;
    time: string;
    category: string;
    description: string;
    amount: number;
    method: string;
    channelBreakdown: CarwashTransactionChannel[];
    recordedBy: string;
    /** The shift resolved and stamped when the entry was created. */
    shift?: string | null;
    source?: string;
    orderId?: number | null;
    orderNo?: string | null;
    customer?: string | null;
    vehicle?: string | null;
    plate?: string | null;
    attachments?: CarwashAttachment[];
}

export interface CarwashAttachment {
    id: number | string;
    name: string;
    size: string;
    /** Demo files have no stored object to serve. */
    url?: string | null;
    /** Images open in the lightbox; other files are downloaded. */
    isImage?: boolean;
}

export interface CarwashCashSummary {
    openingBalance: number;
    todayIn: number;
    todayOut: number;
    remainingBalance: number;
    closingBalance: number;
    pendingPayments: number;
}

export interface CarwashOrderSummary {
    total: number;
    served: number;
    awaitingBooking: number;
}

export interface CarwashStockItem {
    id: number;
    sku: string;
    name: string;
    category: string;
    unit: string;
    quantity: number;
    minQuantity: number;
    unitCost: number;
    supplier: string;
    updatedAt: string;
}

export interface CarwashStockMovement {
    id: number;
    itemId: number;
    item: string;
    sku: string;
    type: string;
    quantity: number;
    note: string;
    date: string;
    time: string;
    by: string;
}

export interface CarwashModule {
    key: string;
    label: string;
    caption: string;
    icon: string;
    route?: string;
}

export interface CarwashAdminModule extends CarwashModule {
    href: string | null;
    enabled: boolean;
    active: boolean;
    children?: CarwashAdminModule[];
}

export interface CarwashAdminAction {
    label: string;
    href: string;
    method: 'get' | 'post';
}

export interface CarwashRole {
    key: string;
    name: string;
    description: string;
    accent: string;
    icon: string;
}

export interface CarwashPersona {
    id: number;
    name: string;
    initials: string;
    shift: string;
    avatar: string | null;
}

export interface CarwashTransactionShiftOption {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    time: string;
}

export interface CarwashTransactionShiftAssignment {
    mode: 'fixed' | 'schedule';
    label: string;
    caption: string;
    shifts: CarwashTransactionShiftOption[];
}

export interface CarwashTimezone {
    id: string;
    code: string;
}

export interface CarwashStaff {
    id: number;
    name: string;
    email: string;
    phone: string;
    role: string;
    shift: string;
    status: string;
    lastActive: string;
    initials: string;
}

export type CarwashRoleMatrix = Record<string, string[]>;

export interface CarwashStat {
    label: string;
    value: string;
    caption: string;
    delta: number | null;
    trend: 'up' | 'down' | 'flat';
    icon: string;
}

export interface CarwashRevenuePoint {
    day: string;
    date: string;
    revenue: number;
    transactions: number;
    expense: number;
}

/** One bar of the report chart, already aggregated by day or by month. */
export interface CarwashTrendPoint {
    label: string;
    caption: string;
    revenue: number;
    expense: number;
    transactions: number;
}

/** The active report range, plus the bounds the filter may select within. */
/** The day an operational module is focused on; '' shows every date. */
export interface CarwashDateFilter {
    date: string;
    today: string;
    earliest: string;
    latest: string;
    label: string;
    /** The outlet's zone, so a browser elsewhere still prints its clock. */
    timezone: string;
}

export interface CarwashReportFilters {
    from: string;
    to: string;
    label: string;
    granularity: 'harian' | 'bulanan';
    days: number;
    today: string;
    earliest: string;
}

export interface CarwashTopService {
    name: string;
    orders: number;
    revenue: number;
}

export interface CarwashCustomerActivity {
    newCustomers: number;
    returningCustomers: number;
    churnRisk: number;
    stampsIssued: number;
    stampsRedeemed: number;
    rewardsClaimed: number;
    averageVisitsPerCustomer: number;
}

export interface CarwashBookingSummary {
    total: number;
    scheduled: number;
    completed: number;
    cancelled: number;
    showRate: number;
    peakSlot: string;
}

export interface CarwashInventorySummary {
    totalItems: number;
    lowStock: number;
    stockValue: number;
    movementsThisWeek: number;
    topConsumed: string;
}

/**
 * Props shared by every admin module page via `AdminController::page()`.
 *
 * The index signature satisfies Inertia's `PageProps` constraint so the shells
 * can read these through `usePage()`.
 */
export interface CarwashAdminShellProps {
    mode: 'demo' | 'live';
    pageTitle?: string;
    brand: CarwashBrand;
    notifications: CarwashNotification[];
    timezone: CarwashTimezone;
    role: CarwashRole;
    modules: CarwashAdminModule[];
    persona: CarwashPersona;
    transactionShift: CarwashTransactionShiftAssignment;
    profileHref: string | null;
    headerAction: CarwashAdminAction | null;
    exitAction: CarwashAdminAction;
    [key: string]: unknown;
}

export interface CarwashAdminDashboardProps extends CarwashAdminShellProps {
    filterUrl: string;
    stats: CarwashStat[];
    filters: CarwashDateFilter;
    shifts: CarwashShift[];
    orderSummary: CarwashOrderSummary;
    cashSummary: CarwashCashSummary;
}

/** Props shared by every customer portal page via `MemberController::page()`. */
export interface CarwashMemberShellProps {
    brand: CarwashBrand;
    member: CarwashMember;
    notifications: CarwashNotification[];
    [key: string]: unknown;
}
