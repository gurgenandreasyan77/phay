import type {
  ButtonHTMLAttributes,
  CSSProperties,
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TdHTMLAttributes,
  TextareaHTMLAttributes,
  ThHTMLAttributes,
} from "react";

export function cn(...parts: Array<string | false | null | undefined>): string {
  return parts.filter(Boolean).join(" ");
}

/* ---------------------------------------------------------------- Button */

type ButtonVariant = "primary" | "secondary" | "ghost" | "danger";
type ButtonSize = "sm" | "md";

const buttonBase =
  "inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50";

const buttonVariants: Record<ButtonVariant, string> = {
  primary: "bg-primary text-primary-foreground hover:bg-primary-hover",
  secondary: "border border-border bg-surface text-ink hover:bg-surface-secondary",
  ghost: "text-ink-muted hover:bg-surface-secondary hover:text-ink",
  // Reserved for actions inside an explicit confirmation step. A delete
  // trigger behind a plain confirm() stays `secondary` — red is for the
  // moment of confirming, not for sitting in a toolbar.
  danger: "bg-danger text-white hover:bg-danger/90",
};

const buttonSizes: Record<ButtonSize, string> = {
  sm: "h-8 px-3 text-xs",
  md: "h-10 px-4",
};

export function buttonClasses(variant: ButtonVariant = "primary", size: ButtonSize = "md", className?: string): string {
  return cn(buttonBase, buttonVariants[variant], buttonSizes[size], className);
}

export function Button({
  variant = "primary",
  size = "md",
  className,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: ButtonVariant; size?: ButtonSize }) {
  return <button className={buttonClasses(variant, size, className)} {...props} />;
}

/* ----------------------------------------------------------------- Badge */

export type BadgeTone = "default" | "primary" | "success" | "warning" | "danger" | "info" | "pending";

const badgeTones: Record<BadgeTone, string> = {
  default: "bg-surface-secondary text-ink-muted",
  primary: "bg-primary-light text-primary",
  success: "bg-success-light text-success",
  warning: "bg-warning-light text-warning",
  danger: "bg-danger-light text-danger",
  info: "bg-info-light text-info",
  pending: "bg-pending-light text-pending",
};

export function Badge({
  tone = "default",
  className,
  children,
}: {
  tone?: BadgeTone;
  className?: string;
  children: ReactNode;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium",
        badgeTones[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}

/* ------------------------------------------------------------ Form fields */

const fieldBase =
  "w-full rounded-md border border-border bg-surface text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-light disabled:cursor-not-allowed disabled:bg-surface-secondary disabled:text-ink-faint";

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={cn(fieldBase, "h-10 px-3", className)} {...props} />;
}

export function Select({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select className={cn(fieldBase, "h-10 px-3", className)} {...props} />;
}

export function Textarea({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea className={cn(fieldBase, "px-3 py-2", className)} {...props} />;
}

export const fieldLabelClasses = "text-xs font-medium text-ink-muted";

export function FieldLabel({ children, className }: { children: ReactNode; className?: string }) {
  return <p className={cn(fieldLabelClasses, className)}>{children}</p>;
}

/* ------------------------------------------------------------------- Card */

export function Card({ className, style, children }: { className?: string; style?: CSSProperties; children: ReactNode }) {
  return (
    <div className={cn("rounded-lg border border-border bg-surface p-5", className)} style={style}>
      {children}
    </div>
  );
}

export function SectionCard({
  title,
  description,
  actions,
  children,
}: {
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold text-ink">{title}</h2>
          {description ? <p className="mt-1 max-w-2xl text-sm text-ink-muted">{description}</p> : null}
        </div>
        {actions}
      </div>
      {children}
    </section>
  );
}

/* ------------------------------------------------------------- Page header */

export function PageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="text-xl font-semibold text-ink">{title}</h1>
        {description ? <p className="mt-1 max-w-2xl text-sm text-ink-muted">{description}</p> : null}
      </div>
      {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
    </div>
  );
}

/* ------------------------------------------------------------------ Table */

export function TableWrap({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <div className={cn("overflow-x-auto rounded-lg border border-border bg-surface", className)}>
      <table className="w-full text-left text-sm">{children}</table>
    </div>
  );
}

export function Thead({ children }: { children: ReactNode }) {
  return (
    <thead className="border-b border-border bg-surface-secondary text-xs font-medium text-ink-muted">
      <tr>{children}</tr>
    </thead>
  );
}

export function Th({ children, className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th className={cn("px-4 py-3 font-medium", className)} {...props}>
      {children}
    </th>
  );
}

export function Td({ children, className, ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
  return (
    <td className={cn("px-4 py-3 text-ink", className)} {...props}>
      {children}
    </td>
  );
}

export function Tr({ children, className }: { children: ReactNode; className?: string }) {
  return <tr className={cn("border-b border-border last:border-0 hover:bg-surface-secondary/60", className)}>{children}</tr>;
}

export function EmptyRow({ colSpan, children }: { colSpan: number; children: ReactNode }) {
  return (
    <tr>
      <td colSpan={colSpan} className="px-4 py-10 text-center text-sm text-ink-muted">
        {children}
      </td>
    </tr>
  );
}

export function EmptyCard({ children }: { children: ReactNode }) {
  return (
    <p className="rounded-lg border border-dashed border-border-strong bg-surface px-4 py-8 text-center text-sm text-ink-muted">
      {children}
    </p>
  );
}

/* ------------------------------------------------------------- Form status */

export function FormStatus({
  status,
  savedLabel = "Պահպանված է։",
  errorLabel = "Չհաջողվեց պահպանել։",
}: {
  status: "idle" | "saving" | "saved" | "error";
  savedLabel?: string;
  errorLabel?: string;
}) {
  if (status === "saved") return <span className="text-sm text-success">{savedLabel}</span>;
  if (status === "error") return <span className="text-sm text-danger">{errorLabel}</span>;
  return null;
}
