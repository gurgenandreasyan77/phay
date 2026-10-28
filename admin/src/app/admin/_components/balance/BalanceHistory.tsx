import type { BalanceTransactionDto } from "@/lib/adminApi";
import { Badge, type BadgeTone, cn, EmptyRow, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { formatNumber } from "../tables/shared";
import { formatDay } from "../subscription/shared";
import styles from "./balance.module.css";

const TYPES: Record<BalanceTransactionDto["type"], { label: string; tone: BadgeTone }> = {
  top_up: { label: "Համալրում", tone: "success" },
  charge: { label: "Գանձում", tone: "default" },
  refund: { label: "Վերադարձ", tone: "info" },
  correction: { label: "Ճշգրտում", tone: "warning" },
};

/** "+5 000" / "−3 900": a signed amount in dram. */
function signed(amount: number): string {
  return `${amount > 0 ? "+" : amount < 0 ? "−" : ""}${formatNumber(Math.abs(amount))} դր.`;
}

/** The balance ledger: every top-up, charge, refund and correction, newest first, with the balance after each. */
export function BalanceHistory({
  transactions,
  platform = false,
}: {
  transactions: BalanceTransactionDto[];
  platform?: boolean;
}) {
  return (
    <TableWrap>
      <Thead>
        <Th>Ամսաթիվ</Th>
        <Th>Գործողություն</Th>
        <Th className="text-right">Գումար</Th>
        <Th className="text-right">Մնացորդ</Th>
      </Thead>
      <tbody>
        {transactions.map((t) => (
          <Tr key={t.id}>
            <Td className="whitespace-nowrap text-ink-muted">{formatDay(t.createdAt)}</Td>
            <Td>
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone={TYPES[t.type].tone}>{TYPES[t.type].label}</Badge>
                {t.automatic ? <Badge tone="primary">Ինքնաշխատ</Badge> : null}
              </div>
              <p className="mt-1 text-sm text-ink">{t.description}</p>
              {t.note || (platform && t.by) ? (
                <p className="text-xs text-ink-muted">{[t.note, platform && t.by ? t.by : null].filter(Boolean).join(" · ")}</p>
              ) : null}
            </Td>
            <Td className={cn("whitespace-nowrap text-right font-medium tabular-nums", t.amount > 0 && styles.plus)}>
              {signed(t.amount)}
            </Td>
            <Td className="whitespace-nowrap text-right tabular-nums text-ink-muted">{formatNumber(t.balanceAfter)} դր.</Td>
          </Tr>
        ))}
        {transactions.length === 0 ? <EmptyRow colSpan={4}>Գործողություններ դեռ չկան։</EmptyRow> : null}
      </tbody>
    </TableWrap>
  );
}
