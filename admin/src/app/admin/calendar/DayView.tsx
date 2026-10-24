"use client";

import { useRouter } from "next/navigation";
import { useState, useSyncExternalStore } from "react";
import { Dialog } from "@/ui/dialog/Dialog";
import { DayPanel, type DayPanelProps } from "./DayPanel";

/** Below `lg` the day's panel sits under the grid, out of sight: a phone. */
const PHONE = "(max-width: 1023px)";

function subscribe(onChange: () => void) {
  const query = window.matchMedia(PHONE);
  query.addEventListener("change", onChange);
  return () => query.removeEventListener("change", onChange);
}

/**
 * The chosen day: its panel beside the grid, and on a phone, when a day
 * with orders was tapped, the same panel in a sheet from the bottom, so
 * the orders show without scrolling past the month.
 */
export function DayView({ open, closeHref, ...panel }: DayPanelProps & { open: boolean; closeHref: string }) {
  const router = useRouter();
  const phone = useSyncExternalStore(subscribe, () => window.matchMedia(PHONE).matches, () => false);
  const [sheet, setSheet] = useState(open);

  function close() {
    setSheet(false);
    // Back to the day without `open`, so a refresh doesn't bring it back.
    router.replace(closeHref, { scroll: false });
  }

  return (
    <>
      <DayPanel {...panel} />
      {phone ? (
        <Dialog open={sheet} onClose={close} sheet label={panel.title} className="px-4 pt-3">
          <DayPanel {...panel} onClose={close} />
        </Dialog>
      ) : null}
    </>
  );
}
