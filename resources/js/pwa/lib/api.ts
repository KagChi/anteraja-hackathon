import axios, { type AxiosInstance } from "axios";
import { clearToken, getToken, type Realm } from "./token";

/** Fired when a request comes back 401 while a token was attached. */
export const UNAUTHORIZED_EVENT = "anteraja:unauthorized";

/**
 * A failed API call, normalized so screens render one message and keep the
 * field errors Laravel sends with a 422.
 */
export class ApiError extends Error {
    readonly status: number;
    readonly errors: Record<string, string[]>;
    /** The raw response body, for the fields the screens read directly. */
    readonly payload: Record<string, unknown>;

    constructor(
        status: number,
        message: string,
        errors: Record<string, string[]> = {},
        payload: Record<string, unknown> = {}
    ) {
        super(message);
        this.name = "ApiError";
        this.status = status;
        this.errors = errors;
        this.payload = payload;
    }
}

/** The first validation message, which is the one worth showing on a form. */
export function firstError(error: unknown): string {
    if (error instanceof ApiError) {
        for (const messages of Object.values(error.errors)) {
            if (messages.length > 0) {
                return messages[0];
            }
        }

        return error.message;
    }

    return "Terjadi kesalahan yang tidak terduga.";
}

export interface ApiResult<T> {
    data: T;
}

function toApiError(error: unknown, realm: Realm): ApiError {
    if (!axios.isAxiosError(error)) {
        return new ApiError(0, "Terjadi kesalahan yang tidak terduga.");
    }

    const status = error.response?.status ?? 0;
    const body = error.response?.data as
        | ({ message?: string; errors?: Record<string, string[]> } & Record<string, unknown>)
        | undefined;
    const message =
        body?.message ??
        (status === 0 ? "Tidak dapat menghubungi server. Periksa koneksi Anda." : `Permintaan gagal (${status}).`);

    // An expired or revoked token is not a screen error: drop it and let the
    // app send the visitor back through the login screen for a fresh one.
    if (status === 401 && getToken(realm) !== null) {
        clearToken(realm);
        window.dispatchEvent(new CustomEvent(UNAUTHORIZED_EVENT, { detail: { realm } }));
    }

    return new ApiError(status, message, body?.errors ?? {}, body ?? {});
}

function createHttp(realm: Realm): AxiosInstance {
    const http = axios.create({
        baseURL: "/api",
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest"
        }
    });

    http.interceptors.request.use((config) => {
        const bearer = getToken(realm);

        if (bearer !== null) {
            config.headers.set("Authorization", `Bearer ${bearer}`);
        }

        return config;
    });

    return http;
}

async function get<T>(http: AxiosInstance, realm: Realm, url: string, params?: object): Promise<T> {
    try {
        const response = await http.get<T>(url, { params });

        return response.data;
    } catch (error) {
        throw toApiError(error, realm);
    }
}

async function post<T>(http: AxiosInstance, realm: Realm, url: string, body?: unknown): Promise<T> {
    try {
        const response = await http.post<T>(url, body ?? {});

        return response.data;
    } catch (error) {
        throw toApiError(error, realm);
    }
}

async function postForm<T>(http: AxiosInstance, realm: Realm, url: string, form: FormData): Promise<T> {
    try {
        const response = await http.post<T>(url, form);

        return response.data;
    } catch (error) {
        throw toApiError(error, realm);
    }
}

/* ------------------------------------------------------------------ types */

export interface CatalogProduct {
    id: number;
    name: string;
    slug: string;
    category: string;
    description: string;
    price_idr: number;
    price_label: string;
    unit: string;
    weight_label: string;
    image_url: string | null;
    handling_notes: string;
}

export interface CatalogResponse {
    data: CatalogProduct[];
    meta: { count: number; standard_temp: string };
}

export interface BuyerIdentity {
    id: number;
    name: string;
    phone: string;
    address?: string;
    area?: string | null;
}

export interface AuthResponse {
    data: {
        token: string;
        token_type: string;
        expires_at: string;
        buyer?: BuyerIdentity;
        courier?: { id: number; name: string; email: string; phone: string | null; status: string };
    };
}

export interface OrderStatus {
    code: string;
    label: string;
    tone: string;
}

export interface ClientOrder {
    resi: string;
    status: OrderStatus;
    product: string;
    product_meta: string;
    recipient: string;
    recipient_area: string | null;
    eta_date: string;
    eta_time: string;
    action_required: string | null;
    next_screen: string;
    completed_label: string | null;
    completed_at: string | null;
}

export interface ClientOrderList {
    data: ClientOrder[];
    meta: {
        active_count: number;
        completed_count: number;
        sla_rate: string;
        tab_counts: { active: number; completed: number; issue: number; total: number };
        pagination: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            has_more: boolean;
        };
    };
}

export interface OrderReceipt {
    tracking_number: string;
    status: string;
    status_label: string;
    service_label: string;
    recipient: { name: string | null; phone: string | null; address: string | null; area: string | null };
    scheduled_label: string;
    scheduled_date: string | null;
    window_start: string | null;
    window_end: string | null;
    weight_kg: string | null;
    item_count: number;
    total_idr: number;
    handling_instructions: string | null;
    items: Array<{ name: string; quantity: number; unit: string; price_idr: number; line_total_idr: number }>;
}

export interface ClientOrderCreated {
    message: string;
    data: OrderReceipt;
}

export interface TimelineEntry {
    title: string;
    description: string;
    time: string;
    is_accent: boolean;
}

export interface TrackingView {
    tracking_number: string;
    status: string;
    status_label: string;
    status_tone: string;
    estimated_delivery: string;
    recipient_name: string;
    recipient_area: string;
    courier_name: string | null;
    cooler_box_code: string | null;
    cold_chain: {
        temperature_label: string;
        temperature_state: string;
        chain_state: string;
        is_measured: boolean;
        recorded_at: string | null;
    } | null;
    timeline: TimelineEntry[];
    can_contact_courier: boolean;
    courier_phone: string | null;
    issue_banner: { message: string; url: string } | null;
    product_name: string | null;
    product_meta: string | null;
}

export interface ScheduleView {
    tracking_number: string;
    status: string;
    status_label: string;
    recipient_name: string;
    recipient_address: string;
    cargo_items: Array<{ category: string; name: string; quantity_label: string }>;
    total_weight_kg: number;
    cold_guaranteed_badge: string;
    handling_note: string;
    scheduled_delivery: string;
    has_cold_priority: boolean;
    courier_name: string | null;
    courier_badge: string | null;
    can_action: boolean;
    available_slots: string[];
}

export interface IssueView {
    tracking_number: string;
    recipient_name: string;
    courier_name: string | null;
    product_names: string[];
    issue_category_label: string;
    issue_summary: string;
    decision_message: string;
    decision_code: string | null;
    quality_inspection_note: string | null;
    cold_chain_chip: string | null;
    timeline: TimelineEntry[];
    can_request_replacement: boolean;
    status_label: string;
    replacement_tracking_number: string | null;
    replacement_url: string | null;
}

export interface ScheduleActionResponse {
    success: boolean;
    message: string;
    data: { status: string; tracking_number: string };
}

export interface ReplacementResponse {
    success: boolean;
    message: string;
    replacement_tracking_number?: string;
    data: { replacement_tracking_number?: string; tracking_number?: string };
}

export interface BuyerProfile {
    name: string;
    phone: string;
    address: string;
    area: string | null;
    stats: { active_count: number; completed_count: number; on_time_rate: string };
    support: { whatsapp_url: string; email: string };
}

export interface CourierTask {
    id: string;
    package_id: number;
    age: string;
    tracking: string;
    service: string;
    serviceKey: string;
    window: string;
    windowStatus: string;
    windowStatusTone: string;
    recipient: string;
    phone: string;
    placeType: string;
    address: string;
    instruction: string | null;
    metrics: Array<{ label: string; value: string }>;
    recipientCoordinates: { latitude: number | null; longitude: number | null };
    status: string;
    statusLabel: string;
    stage: string;
    stageLabel: string;
    hasIssue: boolean;
}

export interface CourierTaskBoard {
    newTasks: CourierTask[];
    activeTasks: CourierTask[];
    courier: { name: string; status: string };
}

export interface DeliveryGps {
    destination: { latitude: number | null; longitude: number | null } | null;
    radius_m: number;
    distance_m: number | null;
    inside: boolean | null;
    level: string;
    level_label: string;
    level_tone: string;
    accuracy_m: number | null;
    action: string | null;
    action_label: string | null;
    reasons: Array<{ code?: string; label?: string; detail?: string; severity?: string }>;
    can_request_review: boolean;
    requested_action: string | null;
    lock: { id: number; status: string; status_label: string; created_at: string | null } | null;
}

export interface CourierDelivery {
    delivery: {
        tracking: string;
        service: string;
        serviceType: string;
        recipient: string;
        phone: string;
        phoneHref: string;
        address: string;
        instruction: string | null;
        mapLabel: string;
        mapHref: string;
        status: string;
        statusLabel: string;
        stage: string;
        stageLabel: string;
        canRequestConfirmation: boolean;
        canDepart: boolean;
        canArrive: boolean;
        canProof: boolean;
        canReportIssue: boolean;
        hasOpenIssue: boolean;
        gps: DeliveryGps;
        /** Packing checks the courier answers before departure, keyed by item. */
        checklistItems: Record<string, string>;
    };
}

export interface CourierProofForm {
    proofForm: {
        tracking: string;
        service: string;
        recipient: string;
        relations: string[];
        notesPlaceholder: string;
    };
}

export interface CourierIssueForm {
    issueForm: {
        tracking: string;
        recipient: string;
        reporter: string;
        options: Array<{ value: string; label: string }>;
    };
}

export interface CourierHistory {
    history: Array<{ resi: string; recipient: string; completedAt: string; status: string }>;
    summary: { completedToday: string; completedCount: number; onTimeRate: string };
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number };
}

export interface CourierProfile {
    profile: {
        name: string;
        status: string;
        id: string;
        phone: string;
        hub: string;
        vehicle: string;
        stats: Array<{ label: string; value: string }>;
    };
}

export interface DistanceResponse {
    distance_km: number;
    eta_minutes: number;
}

export interface ActionMessage {
    message: string;
    redirect?: string;
}

/** The fix window every location-checked action ships. */
export interface GpsFields {
    latitude?: number | null;
    longitude?: number | null;
    device_timestamp?: number | null;
    gps_signals?: Array<{ latitude: number; longitude: number; accuracy: number | null; timestamp: number }>;
}

/* --------------------------------------------------------------- client API */

const clientHttp = createHttp("client");
const courierHttp = createHttp("courier");

export const clientApi = {
    catalog: {
        list: () => get<CatalogResponse>(clientHttp, "client", "/client/catalog"),
        show: (slug: string) =>
            get<{ data: CatalogProduct }>(clientHttp, "client", `/client/catalog/${encodeURIComponent(slug)}`)
    },
    auth: {
        login: (phone: string, password: string) =>
            post<AuthResponse>(clientHttp, "client", "/client/auth/login", { phone, password }),
        register: (payload: {
            name: string;
            phone: string;
            password: string;
            password_confirmation: string;
            address: string;
            area?: string | null;
            address_type?: string | null;
        }) => post<AuthResponse>(clientHttp, "client", "/client/auth/register", payload),
        logout: () => post<ActionMessage>(clientHttp, "client", "/client/auth/logout")
    },
    me: () => get<{ data: BuyerProfile }>(clientHttp, "client", "/client/me"),
    orders: {
        list: (tab: "active" | "completed", query?: string) =>
            get<ClientOrderList>(clientHttp, "client", "/client/orders", { tab, q: query }),
        create: (payload: {
            recipient: {
                name: string;
                phone: string;
                address: string;
                area: string | null;
                address_type: string;
                latitude: number;
                longitude: number;
            };
            service_type: string;
            scheduled_date: string;
            window_start: string;
            window_end: string;
            handling_instructions: string | null;
            items: Array<{ product_id: number; quantity: number }>;
        }) => post<ClientOrderCreated>(clientHttp, "client", "/client/orders", payload),
        show: (resi: string) =>
            get<{ data: ClientOrder }>(clientHttp, "client", `/client/orders/${encodeURIComponent(resi)}`),
        tracking: (resi: string) =>
            get<{ success: boolean; data: TrackingView }>(
                clientHttp,
                "client",
                `/client/orders/${encodeURIComponent(resi)}/tracking`
            ),
        schedule: (resi: string) =>
            get<{ success: boolean; data: ScheduleView }>(
                clientHttp,
                "client",
                `/client/orders/${encodeURIComponent(resi)}/schedule`
            ),
        confirmSchedule: (resi: string) =>
            post<ScheduleActionResponse>(
                clientHttp,
                "client",
                `/client/orders/${encodeURIComponent(resi)}/schedule/confirm`
            ),
        reschedule: (resi: string, date: string, slot: string) =>
            post<ScheduleActionResponse>(
                clientHttp,
                "client",
                `/client/orders/${encodeURIComponent(resi)}/schedule/reschedule`,
                { date, slot }
            ),
        issue: (resi: string) =>
            get<{ success: boolean; data?: IssueView; redirect_url?: string }>(
                clientHttp,
                "client",
                `/client/orders/${encodeURIComponent(resi)}/issue`
            ),
        requestResend: (resi: string) =>
            post<ReplacementResponse>(clientHttp, "client", `/client/orders/${encodeURIComponent(resi)}/issue/resend`)
    }
};

/* -------------------------------------------------------------- courier API */

export const courierApi = {
    auth: {
        login: (email: string, password: string) =>
            post<AuthResponse>(courierHttp, "courier", "/courier/auth/login", { email, password }),
        logout: () => post<ActionMessage>(courierHttp, "courier", "/courier/auth/logout")
    },
    tasks: {
        board: () => get<CourierTaskBoard>(courierHttp, "courier", "/courier/tasks"),
        distance: (resi: string, latitude: number, longitude: number) =>
            get<DistanceResponse | "">(courierHttp, "courier", `/courier/tasks/${encodeURIComponent(resi)}/distance`, {
                lat: latitude,
                lng: longitude
            }),
        accept: (resi: string) =>
            post<ActionMessage>(courierHttp, "courier", `/courier/tasks/${encodeURIComponent(resi)}/accept`),
        reject: (resi: string, reason: string) =>
            post<ActionMessage>(courierHttp, "courier", `/courier/tasks/${encodeURIComponent(resi)}/reject`, {
                reason
            })
    },
    deliveries: {
        show: (resi: string) =>
            get<CourierDelivery>(courierHttp, "courier", `/courier/deliveries/${encodeURIComponent(resi)}`),
        depart: (resi: string, fields: GpsFields, checklist: Record<string, boolean>) =>
            post<ActionMessage & { hasIssue?: boolean }>(
                courierHttp,
                "courier",
                `/courier/deliveries/${encodeURIComponent(resi)}/depart`,
                { ...fields, checklist }
            ),
        arrive: (resi: string, fields: GpsFields) =>
            post<ActionMessage>(
                courierHttp,
                "courier",
                `/courier/deliveries/${encodeURIComponent(resi)}/arrive`,
                fields
            ),
        requestConfirmation: (resi: string, scheduledDate?: string, window?: string) =>
            post<ActionMessage>(
                courierHttp,
                "courier",
                `/courier/deliveries/${encodeURIComponent(resi)}/request-confirmation`,
                scheduledDate && window ? { scheduled_date: scheduledDate, scheduled_time_window: window } : {}
            ),
        requestReview: (resi: string, action: string, note?: string) =>
            post<ActionMessage>(courierHttp, "courier", `/courier/deliveries/${encodeURIComponent(resi)}/gps-review`, {
                action,
                note
            }),
        proofForm: (resi: string) =>
            get<CourierProofForm>(courierHttp, "courier", `/courier/deliveries/${encodeURIComponent(resi)}/proof`),
        confirm: (resi: string, form: FormData) =>
            postForm<ActionMessage>(
                courierHttp,
                "courier",
                `/courier/deliveries/${encodeURIComponent(resi)}/proof`,
                form
            ),
        issueForm: (resi: string) =>
            get<CourierIssueForm>(courierHttp, "courier", `/courier/deliveries/${encodeURIComponent(resi)}/issue`),
        reportIssue: (resi: string, form: FormData) =>
            postForm<ActionMessage>(
                courierHttp,
                "courier",
                `/courier/deliveries/${encodeURIComponent(resi)}/issue`,
                form
            )
    },
    history: () => get<CourierHistory>(courierHttp, "courier", "/courier/history"),
    profile: () => get<CourierProfile>(courierHttp, "courier", "/courier/profile")
};
