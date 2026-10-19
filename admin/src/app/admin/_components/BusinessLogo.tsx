import { cn } from "./ui";

/** A business's logo, or the first letter of its name when it has none. */
export function BusinessLogo({
  name,
  logo,
  size = "md",
  className,
}: {
  name: string;
  logo?: string | null;
  size?: "sm" | "md" | "lg";
  className?: string;
}) {
  const box = { sm: "h-8 w-8 text-xs", md: "h-10 w-10 text-sm", lg: "h-20 w-20 text-2xl" }[size];

  if (logo) {
    return (
      // eslint-disable-next-line @next/next/no-img-element -- an uploaded file served by the API, not a build asset
      <img
        src={logo}
        alt={name}
        className={cn(box, "shrink-0 rounded-md border border-border bg-white object-contain", className)}
      />
    );
  }

  return (
    <span
      className={cn(
        box,
        "flex shrink-0 items-center justify-center rounded-md bg-primary-light font-semibold text-primary",
        className,
      )}
    >
      {name.trim().slice(0, 1).toUpperCase() || "?"}
    </span>
  );
}
