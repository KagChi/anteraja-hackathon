/**
 * Typed access to the admin JSON API.
 *
 * Every admin screen reads and writes through this module. The shapes mirror
 * the admin controllers, so a payload change breaks the build instead of
 * silently emptying a table.
 */
import axios, { type AxiosRequestConfig } from "axios";

/** Status and issue tones, mirroring App\Enums\Tone. */
export type Tone = "neutral" | "muted" | "warning" | "info" | "danger" | "dark" | "success";

export interface StaffUser {
    id: number;
    name: string;
    email: string;
    role: string;
    phone: string | null;
}

export interface Paginator {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from?: number | null;
    to?: number | null;
}

export interface CourierOption {
    id: number;
    name: string;
    phone: string | null;
}

export interface StatusOption {
    value: string;
    label: string;
}

export interface ProductOption {
    id: number;
    name: string;
    handling_notes: string | null;
}

export interface BuyerOption {
    id: number;
    name: string;
    phone: string | null;
    address: string;
    area: string | null;
}

/** Which action the queue offers for a row; mirrors Package::availableAdminActions(). */
export type AvailableAction = "issue_detail" | "assign_courier" | "reschedule" | "detail";

export interface ShipmentRow {
    id: number;
    tracking: string;
    buyer: string;
    location: string;
    schedule: string;
    courier: string | null;
    courier_id: number | null;
    status: string;
    statusKey: string;
    tone: Tone;
    hasIssue: boolean;
    availableAction: AvailableAction;
    /** Which door the shipment came through: the hub console or the app. */
    source: "ADMIN" | "CLIENT_APP" | null;
    sourceLabel: string | null;
    sourceBadge: string | null;
}

export interface TimelineEntry {
    label: string;
    time: string;
    detail: string;
    active?: boolean;
}

export interface ShipmentIndexFilters {
    q: string | null;
    status: string | null;
    courier: string | null;
    schedule: string;
    date: string | null;
    per_page: number;
    source: string | null;
}

export interface ShipmentIndexResponse {
    shipments: ShipmentRow[];
    paginator: Paginator;
    filters: ShipmentIndexFilters;
    couriers: CourierOption[];
    statusOptions: StatusOption[];
}

export interface ShipmentQuery {
    q?: string;
    status?: string;
    courier?: string;
    date?: string;
    page?: number;
    per_page?: number;
    source?: string;
}

export interface DeliveryRecord {
    received_by: string | null;
    recipient_relation: string | null;
    photo_url: string | null;
    receipt_note: string | null;
    delivered_at: string | null;
}

export interface PackageDetail {
    id: number;
    tracking_number: string;
    status: string;
    status_ref: { code: string; label: string } | null;
    buyer: { name: string; phone: string | null; address: string; area: string | null } | null;
    courier: { id: number; name: string } | null;
    courier_id: number | null;
    scheduled_at: string | null;
    scheduled_end_at: string | null;
    weight_kg: string | null;
    koli_count: number | null;
    cooler_box_code: string | null;
    service_type: string;
    items: Array<{ id: number; quantity: number; product: { name: string } | null }>;
    delivery: DeliveryRecord | null;
}

export interface ShipmentDetailResponse {
    package: PackageDetail;
    timeline: TimelineEntry[];
    client_url: string;
    scheduleLabel: string;
    scheduledDate: string | null;
    statusLabel: string;
    tone: Tone;
    proofPhotoUrl: string | null;
    issuePhotoUrl: string | null;
}

export interface IssueReportPayload {
    id: number;
    issue_category: string;
    description: string | null;
    photo_url: string | null;
    quality_inspection_note: string | null;
    decision: string | null;
    decision_reason: string | null;
    reported_at: string | null;
}

export interface ConditionCheckPayload {
    id: number;
    is_normal: boolean;
    checklist: Record<string, { answer?: boolean; detail?: string }> | null;
    notes: string | null;
    temperature_c: number | null;
    checked_at: string | null;
}

export interface DecisionOption {
    value: string;
    label: string;
    helpText: string;
}

export interface ChecklistItem {
    key: string;
    label: string;
    answered: boolean;
    detail: string;
}

export interface ExceptionDetailResponse {
    package: PackageDetail;
    issueReport: IssueReportPayload;
    conditionCheck: ConditionCheckPayload;
    statusLabel: string;
    tone: Tone;
    resolutionStatus: string;
    resolutionTone: Tone;
    category: { key: string; label: string };
    allowedDecisions: DecisionOption[];
    checklist: ChecklistItem[];
    timeline: TimelineEntry[];
    reportedAtFormatted: string | null;
    photoTemporaryUrl: string | null;
}

export interface ReportKpis {
    total_shipments: number;
    fleet_capacity_pct: number;
    delivered_count: number;
    success_rate_pct: number;
    issue_count: number;
    incident_rate_pct: number;
}

export interface ReportIssueRow {
    id: number;
    tracking: string;
    buyer: string;
    courier: string;
    category: string;
    decision: string;
    resolutionStatus: string;
    resolutionTone: Tone;
    reported_at: string;
}

export interface ReportResponse {
    date: string;
    dateLabel: string;
    kpis: ReportKpis;
    issues: ReportIssueRow[];
    categoriesOnDate: string[];
    selectedCategory: string | null;
    paginator: Paginator;
}

export interface HistoryRow {
    id: number;
    tracking: string;
    buyer: string;
    courier: string;
    completed_at: string;
    status: string;
    statusKey: string;
    tone: Tone;
}

export interface HistoryResponse {
    history: HistoryRow[];
    filters: { q: string | null; status: string | null; page: number };
    paginator: Paginator;
}

/** Location validation: one verdict row the hub can inspect. */
export interface GpsReason {
    code: string;
    label: string;
    detail: string;
    severity: "strong" | "weak";
}

export interface GpsCheckRow {
    id: number;
    tracking: string;
    buyer: string;
    courier: string;
    action: string | null;
    action_label: string | null;
    level: string | null;
    level_label: string | null;
    tone: Tone;
    reasons: GpsReason[];
    distance_m: number | null;
    radius_m: number | null;
    inside: boolean | null;
    accuracy_m: number | null;
    applied: boolean;
    override_status: string | null;
    checked_at: string | null;
}

export interface GpsLockRow {
    id: number;
    tracking: string;
    buyer: string;
    courier: string;
    action: string | null;
    action_label: string | null;
    status: string;
    status_label: string;
    tone: Tone;
    reasons: GpsReason[];
    decision_reason: string | null;
    decided_by: string | null;
    decided_at: string | null;
    created_at: string | null;
}

export interface GpsChecksResponse {
    tab: "pending" | "resolved";
    checks: GpsCheckRow[];
    locks: GpsLockRow[];
    summary: {
        pending_requests: number;
        blocked_today: number;
        suspect_today: number;
        outside_radius_today: number;
    };
    radius: Record<string, number>;
}

export interface GeofenceSegment {
    service_type: string;
    label: string;
    sla: string;
    description: string;
    min: number;
    max: number;
    step: number;
    radius_m: number;
    default_m: number;
}

export interface GeofenceResponse {
    segments: GeofenceSegment[];
    updated_at: string | null;
}

export interface GpsDecisionPayload {
    decision: "APPROVED" | "REJECTED";
    decision_reason: string;
}

/** Public resi lookup: status only, never anything that names the recipient. */
export interface TrackTimelineEntry {
    label: string;
    time: string;
    detail: string;
    is_accent: boolean;
}

export interface TrackResult {
    resi: string;
    status: { code: string; label: string; tone: Tone };
    service: string;
    category: string;
    cold_chain: string;
    destination_city: string | null;
    schedule_label: string;
    updated_label: string;
    timeline: TrackTimelineEntry[];
}

export interface ShipmentCreateOptions {
    products: ProductOption[];
    buyers: BuyerOption[];
    couriers: CourierOption[];
}

export interface NewBuyerPayload {
    name: string;
    phone: string;
    address: string;
    area?: string;
}

export interface CreateShipmentPayload {
    buyer_id?: number;
    buyer?: NewBuyerPayload;
    items: Array<{ product_id: number; quantity: number }>;
    scheduled_date: string;
    window_start: string;
    window_end: string;
    weight_kg: number;
    cooler_box_code?: string;
    service_type?: string;
    koli_count?: number;
}

export interface DecisionPayload {
    decision: string;
    decision_reason: string;
    quality_inspection_note?: string;
}

/**
 * A failed API call, normalized so screens can render one message and keep the
 * field errors Laravel sends with a 422.
 */
export class ApiError extends Error {
    readonly status: number;
    readonly errors: Record<string, string[]>;

    constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
        super(message);
        this.name = "ApiError";
        this.status = status;
        this.errors = errors;
    }
}

/**
 * Same-origin so the session cookie rides along; axios reads the XSRF-TOKEN
 * cookie the web group sets and echoes it as X-XSRF-TOKEN, which the API group
 * validates.
 */
export const http = axios.create({
    baseURL: "/api",
    withCredentials: true,
    headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest"
    }
});

function toApiError(error: unknown): ApiError {
    if (axios.isAxiosError(error)) {
        const status = error.response?.status ?? 0;
        const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
        const message =
            data?.message ??
            (status === 0 ? "Tidak dapat menghubungi server. Periksa koneksi Anda." : `Permintaan gagal (${status}).`);

        return new ApiError(status, message, data?.errors ?? {});
    }

    return new ApiError(0, "Terjadi kesalahan yang tidak terduga.");
}

async function get<T>(url: string, params?: object): Promise<T> {
    try {
        const response = await http.get<T>(url, { params });
        return response.data;
    } catch (error) {
        throw toApiError(error);
    }
}

async function post<T>(url: string, body?: unknown, config?: AxiosRequestConfig): Promise<T> {
    try {
        const response = await http.post<T>(url, body ?? {}, config);
        return response.data;
    } catch (error) {
        throw toApiError(error);
    }
}

async function put<T>(url: string, body?: unknown): Promise<T> {
    try {
        const response = await http.put<T>(url, body ?? {});
        return response.data;
    } catch (error) {
        throw toApiError(error);
    }
}

/** Drop empty filter values so the URL never carries status= or q= noise. */
export function toQueryString(params: object): string {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value === undefined || value === null || value === "") continue;
        search.set(key, String(value));
    }
    const query = search.toString();
    return query === "" ? "" : `?${query}`;
}

export const api = {
    track: {
        show: (resi: string) => get<{ data: TrackResult }>("/track/" + encodeURIComponent(resi))
    },
    auth: {
        login: (email: string, password: string, remember = false) =>
            post<{ message: string; user: StaffUser }>("/auth/login", { email, password, remember }),
        logout: () => post<{ message: string }>("/auth/logout"),
        me: () => get<{ user: StaffUser }>("/auth/me")
    },
    shipments: {
        index: (query: ShipmentQuery) => get<ShipmentIndexResponse>("/admin/shipments", query),
        createOptions: () => get<ShipmentCreateOptions>("/admin/shipments/create"),
        store: (payload: CreateShipmentPayload) =>
            post<{ message: string; package: PackageDetail }>("/admin/shipments", payload),
        show: (resi: string) => get<ShipmentDetailResponse>(`/admin/shipments/${encodeURIComponent(resi)}`),
        assign: (resi: string, courierId: number) =>
            post<{ message: string }>(`/admin/shipments/${encodeURIComponent(resi)}/assign`, {
                courier_id: courierId
            }),
        reschedule: (resi: string, scheduledDate: string, window: string) =>
            post<{ message: string }>(`/admin/shipments/${encodeURIComponent(resi)}/reschedule`, {
                scheduled_date: scheduledDate,
                scheduled_time_window: window
            })
    },
    exceptions: {
        show: (resi: string) => get<ExceptionDetailResponse>(`/admin/exceptions/${encodeURIComponent(resi)}`),
        decide: (resi: string, payload: DecisionPayload) =>
            post<{ message: string }>(`/admin/exceptions/${encodeURIComponent(resi)}/decision`, payload)
    },
    reports: {
        index: (query: { date?: string; category?: string; page?: number }) =>
            get<ReportResponse>("/admin/reports", query)
    },
    history: {
        index: (query: { q?: string; status?: string; page?: number }) => get<HistoryResponse>("/admin/history", query)
    },
    gps: {
        checks: (query: { status?: string; level?: string } = {}) => get<GpsChecksResponse>("/admin/gps-checks", query),
        decide: (id: number, payload: GpsDecisionPayload) =>
            post<{ message: string }>(`/admin/gps-checks/${id}/decision`, payload)
    },
    geofence: {
        show: () => get<GeofenceResponse>("/admin/geofence"),
        update: (policies: Record<string, number>) =>
            put<{ message: string } & GeofenceResponse>("/admin/geofence", { policies })
    }
};
