import { useMemo } from "react"
import { Cell, Label, Pie, PieChart } from "recharts"

import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "#/components/ui/chart.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { BookingStatusCount } from "#/hooks/use-dashboard.ts"

/**
 * Empat status dipetakan ke slot kategorikal dengan urutan tetap: status
 * tidak boleh berganti warna hanya karena salah satunya bernilai nol.
 * Paletnya sudah divalidasi CVD-safe untuk mode terang dan gelap.
 */
const SLOT: Record<string, string> = {
  pending: "var(--chart-cat-1)",
  confirmed: "var(--chart-cat-2)",
  done: "var(--chart-cat-3)",
  cancelled: "var(--chart-cat-4)",
}

export function BookingStatusChart({ items }: { items: BookingStatusCount[] }) {
  const { t } = useTrans()

  const total = useMemo(
    () => items.reduce((sum, item) => sum + item.count, 0),
    [items],
  )

  const config = useMemo<ChartConfig>(
    () =>
      Object.fromEntries(
        items.map((item) => [
          item.status,
          { label: item.label, color: SLOT[item.status] },
        ]),
      ),
    [items],
  )

  if (total === 0) {
    return (
      <p className="flex h-56 items-center justify-center text-sm text-muted-foreground">
        {t("dashboard.empty")}
      </p>
    )
  }

  return (
    <div className="flex flex-col items-center gap-4 sm:flex-row">
      <ChartContainer config={config} className="h-48 w-48 shrink-0">
        <PieChart>
          <ChartTooltip content={<ChartTooltipContent nameKey="label" />} />
          <Pie
            data={items}
            dataKey="count"
            nameKey="label"
            innerRadius={54}
            outerRadius={78}
            // Jeda tipis antar irisan; tanpa itu dua warna bersebelahan
            // terbaca menyatu di layar kecil.
            paddingAngle={2}
            strokeWidth={2}
            animationDuration={700}
            animationEasing="ease-out"
          >
            {items.map((item) => (
              <Cell
                key={item.status}
                fill={SLOT[item.status]}
                stroke="var(--card)"
              />
            ))}
            <Label
              position="center"
              content={({ viewBox }) =>
                viewBox && "cx" in viewBox ? (
                  <text
                    x={viewBox.cx}
                    y={viewBox.cy}
                    textAnchor="middle"
                    dominantBaseline="middle"
                  >
                    <tspan
                      x={viewBox.cx}
                      y={viewBox.cy}
                      className="fill-foreground text-xl font-semibold tabular-nums"
                    >
                      {total}
                    </tspan>
                    <tspan
                      x={viewBox.cx}
                      y={(viewBox.cy ?? 0) + 18}
                      className="fill-muted-foreground text-xs"
                    >
                      {t("dashboard.total")}
                    </tspan>
                  </text>
                ) : null
              }
            />
          </Pie>
        </PieChart>
      </ChartContainer>

      {/*
        Legend dengan angka, bukan hanya warna: sudut irisan sulit
        dibandingkan saat nilainya berdekatan, dan kontras dua slot di mode
        terang berada di bawah 3:1 sehingga labelnya wajib terlihat.
      */}
      <ul className="w-full space-y-1.5">
        {items.map((item) => (
          <li
            key={item.status}
            className="flex items-center gap-2 text-sm"
          >
            <span
              aria-hidden
              className="size-2.5 shrink-0 rounded-[3px]"
              style={{ background: SLOT[item.status] }}
            />
            <span className="flex-1 truncate text-muted-foreground">
              {item.label}
            </span>
            <span className="font-medium tabular-nums">{item.count}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
