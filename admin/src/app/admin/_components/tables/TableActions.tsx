"use client";

import { useState } from "react";
import { CalendarRange } from "lucide-react";
import type { AdminDataTableDto, DataTableSection } from "@/lib/adminApi";
import { Button } from "@/ui";
import { ReshapeDialog } from "./Reshape";

/** On a table's page: split it into periods (or join it with others). */
export function ReshapeTableButton({
  section,
  table,
  tables,
}: {
  section: DataTableSection;
  table: AdminDataTableDto;
  /** The section's current tables, to add more of them. */
  tables: AdminDataTableDto[];
}) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <Button variant="secondary" onClick={() => setOpen(true)}>
        <CalendarRange className="h-4 w-4" />
        Բաժանել ըստ ժամանակի
      </Button>
      {open ? (
        <ReshapeDialog
          open
          onClose={() => setOpen(false)}
          section={section}
          tables={tables.some((t) => t.id === table.id) ? tables : [table, ...tables]}
          initialIds={[table.id]}
        />
      ) : null}
    </>
  );
}
