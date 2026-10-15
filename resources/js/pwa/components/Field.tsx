import { cx } from "@/lib/cx";

const FIELD_CLASSES =
    "w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-space-md font-body-md text-body-md text-on-surface transition-colors placeholder:text-secondary focus:border-primary focus:outline-none";

const LABEL_CLASSES = "font-label-md text-label-md text-secondary";

interface TextFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    type?: "text" | "tel" | "email" | "password" | "number";
    inputMode?: "text" | "tel" | "email" | "numeric" | "decimal";
    autoComplete?: string;
    supporting?: string;
    min?: number;
    max?: number;
}

export function TextField({
    label,
    value,
    onChange,
    placeholder,
    type = "text",
    inputMode,
    autoComplete,
    supporting,
    min,
    max
}: TextFieldProps) {
    return (
        <label className="flex w-full flex-col gap-1">
            <span className={LABEL_CLASSES}>{label}</span>
            <input
                type={type}
                value={value}
                placeholder={placeholder}
                inputMode={inputMode}
                autoComplete={autoComplete}
                min={min}
                max={max}
                onChange={(event) => onChange(event.target.value)}
                className={cx(FIELD_CLASSES, "h-11")}
            />
            {supporting === undefined ? null : (
                <span className="font-label-sm text-label-sm text-secondary">{supporting}</span>
            )}
        </label>
    );
}

interface TextAreaFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    rows?: number;
    supporting?: string;
}

export function TextAreaField({ label, value, onChange, placeholder, rows = 3, supporting }: TextAreaFieldProps) {
    return (
        <label className="flex w-full flex-col gap-1">
            <span className={LABEL_CLASSES}>{label}</span>
            <textarea
                value={value}
                rows={rows}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                className={cx(FIELD_CLASSES, "py-space-sm leading-relaxed")}
            />
            {supporting === undefined ? null : (
                <span className="font-label-sm text-label-sm text-secondary">{supporting}</span>
            )}
        </label>
    );
}

interface SelectOption {
    value: string;
    label: string;
}

interface SelectFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: SelectOption[];
}

export function SelectField({ label, value, onChange, options }: SelectFieldProps) {
    return (
        <label className="flex w-full flex-col gap-1">
            <span className={LABEL_CLASSES}>{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={cx(FIELD_CLASSES, "h-11 appearance-none pr-8")}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

interface InfoCardProps {
    label?: string;
    children: React.ReactNode;
}

/** The quiet note card the flow screens put under a form or a summary. */
export function InfoCard({ label, children }: InfoCardProps) {
    return (
        <section className="flex w-full flex-col gap-2 rounded-xl bg-surface-container-low p-space-lg">
            {label === undefined ? null : (
                <span className="font-label-sm text-label-sm text-secondary uppercase">{label}</span>
            )}
            {children}
        </section>
    );
}
