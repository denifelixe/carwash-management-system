/**
 * Shapes mirroring the PHP demo-data providers in `app/Support/Carwash`.
 */

export interface CarwashBrand {
    name: string;
    logo: string;
    whatsapp: string;
    instagram: string;
    stampTarget: number;
    stampReward: string;
    today: string;
}

export interface CarwashShift {
    id: string;
    name: string;
    time: string;
    cashier: string;
    initials: string;
    revenue: number;
    transactions: number;
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
    duration: number;
    stamps: number;
    icon: string;
    description: string;
    popular: boolean;
    isActive: boolean;
}

export interface CarwashReward {
    id: number;
    name: string;
    description: string;
    requiredStamps: number;
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

export interface CarwashVehicle {
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

export interface CarwashOrder {
    id: number;
    orderNo: string;
    invoice: string;
    time: string;
    customerId: number | null;
    customer: string;
    phone: string;
    vehicle: string;
    plate: string;
    items: string;
    serviceIds: number[];
    total: number;
    payment: string;
    paymentStatus: string;
    status: string;
    stampsEarned: number;
    crew: string;
    bay: string;
    source: string;
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
    serviceId: number;
    date: string;
    time: string;
    dayLabel: string;
    status: string;
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

export interface CarwashCartLine {
    service: CarwashService;
    quantity: number;
}

export interface CarwashMoneyEntry {
    id: number;
    ref: string;
    date: string;
    time: string;
    category: string;
    description: string;
    amount: number;
    method: string;
    recordedBy: string;
    source?: string;
    attachment?: CarwashAttachment | null;
}

export interface CarwashAttachment {
    name: string;
    size: string;
}

export interface CarwashCashSummary {
    openingBalance: number;
    todayIn: number;
    todayOut: number;
    closingBalance: number;
    pendingPayments: number;
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
    route: string;
}

export interface CarwashRole {
    key: string;
    name: string;
    description: string;
    accent: string;
    icon: string;
}

export interface CarwashPersona {
    name: string;
    title: string;
    initials: string;
}

export interface CarwashStaff {
    id: number;
    name: string;
    email: string;
    phone: string;
    role: string;
    status: string;
    lastActive: string;
    initials: string;
}

export type CarwashRoleMatrix = Record<string, string[]>;

export interface CarwashStat {
    label: string;
    value: string;
    caption: string;
    delta: number;
    trend: string;
    icon: string;
}

export interface CarwashRevenuePoint {
    day: string;
    date: string;
    revenue: number;
    transactions: number;
}

export interface CarwashMonthlyPoint {
    month: string;
    revenue: number;
    expense: number;
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
    brand: CarwashBrand;
    notifications: CarwashNotification[];
    role: CarwashRole;
    modules: CarwashModule[];
    persona: CarwashPersona;
    [key: string]: unknown;
}

/** Props shared by every customer portal page via `MemberController::page()`. */
export interface CarwashMemberShellProps {
    brand: CarwashBrand;
    member: CarwashMember;
    notifications: CarwashNotification[];
    [key: string]: unknown;
}
