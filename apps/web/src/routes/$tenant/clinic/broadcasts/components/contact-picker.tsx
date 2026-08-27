import { useMemo, useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { HugeiconsIcon } from "@hugeicons/react"
import { Cancel01Icon, Search01Icon, Tick02Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { ScrollArea } from "#/components/ui/scroll-area.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { cn } from "#/lib/utils.ts"

export interface ContactRow {
  id: number
  name: string
  whatsapp?: string | null
}

interface ContactPickerProps {
  tenant: string
  value: number[]
  onChange: (ids: number[]) => void
  enabled: boolean
}

/**
 * Pemilih kontak satu per satu untuk broadcast.
 *
 * Sasaran yang ada sebelumnya semuanya berupa aturan — semua pasien, yang
 * lama tidak datang, yang pernah ambil layanan tertentu. Tidak ada jalan
 * untuk mengirim ke belasan orang tertentu saja, padahal itu yang paling
 * sering dibutuhkan: menyapa peserta promo, atau mengabari sekelompok kecil
 * pasien yang jadwalnya bergeser.
 *
 * Yang sudah dipilih ditampilkan sebagai keping di atas, bukan hanya sebagai
 * centang di dalam daftar: setelah mengetik pencarian baru, daftarnya
 * berganti isi dan pilihan sebelumnya akan hilang dari pandangan padahal
 * masih terpilih.
 */
export function ContactPicker({
  tenant,
  value,
  onChange,
  enabled,
}: ContactPickerProps) {
  const { t } = useTrans()
  const [search, setSearch] = useState("")

  const patients = useQuery({
    queryKey: ["patients", tenant, "contact-picker", search],
    queryFn: () =>
      apiGet<{ data: ContactRow[] }>(`/${tenant}/clinic/patients`, {
        per_page: 50,
        search: search || undefined,
      }),
    enabled,
  })

  const rows = patients.data?.data ?? []
  const selectedSet = useMemo(() => new Set(value), [value])

  // Keping pilihan dirakit dari baris yang sedang termuat; nama yang sudah
  // tergeser oleh pencarian baru tetap terbaca lewat simpanan ini.
  const [known, setKnown] = useState<Record<number, string>>({})

  const remember = (row: ContactRow) =>
    setKnown((prev) => (prev[row.id] ? prev : { ...prev, [row.id]: row.name }))

  const toggle = (row: ContactRow) => {
    remember(row)

    onChange(
      selectedSet.has(row.id)
        ? value.filter((id) => id !== row.id)
        : [...value, row.id],
    )
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs text-muted-foreground">
          {t("broadcast.pick_contacts_hint")}
        </p>
        {value.length > 0 ? (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-7 px-2 text-xs text-muted-foreground hover:text-destructive"
            onClick={() => onChange([])}
          >
            {t("broadcast.clear_selection")}
          </Button>
        ) : null}
      </div>

      {value.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {value.map((id) => (
            <Badge
              key={id}
              variant="secondary"
              className="gap-1 pr-1 font-normal"
            >
              {known[id] ?? `#${id}`}
              <button
                type="button"
                aria-label={t("broadcast.clear_selection")}
                onClick={() => onChange(value.filter((other) => other !== id))}
                className="rounded-sm p-0.5 transition-colors hover:bg-background/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
              >
                <HugeiconsIcon icon={Cancel01Icon} className="size-3" />
              </button>
            </Badge>
          ))}
        </div>
      ) : null}

      <div className="relative">
        <HugeiconsIcon
          icon={Search01Icon}
          className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t("broadcast.search_patients")}
          className="h-8 ps-8 text-xs"
        />
      </div>

      <div className="rounded-md border border-border/60">
        <ScrollArea className="max-h-56">
          {patients.isLoading ? (
            <div className="space-y-1 p-2">
              <Skeleton className="h-7 w-full" />
              <Skeleton className="h-7 w-full" />
              <Skeleton className="h-7 w-full" />
            </div>
          ) : rows.length === 0 ? (
            <p className="p-4 text-center text-xs text-muted-foreground">
              {t("broadcast.no_patient_match")}
            </p>
          ) : (
            <ul className="p-1">
              {rows.map((row) => {
                const selected = selectedSet.has(row.id)

                return (
                  <li key={row.id}>
                    <button
                      type="button"
                      aria-pressed={selected}
                      onClick={() => toggle(row)}
                      className={cn(
                        "flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left transition-colors",
                        "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                        selected ? "bg-muted" : "hover:bg-muted/60",
                      )}
                    >
                      <span
                        aria-hidden
                        className={cn(
                          "flex size-4 shrink-0 items-center justify-center rounded-[4px] border",
                          selected
                            ? "border-primary bg-primary text-primary-foreground"
                            : "border-border",
                        )}
                      >
                        {selected ? (
                          <HugeiconsIcon icon={Tick02Icon} className="size-3" />
                        ) : null}
                      </span>
                      <span className="min-w-0 flex-1 truncate text-xs font-medium">
                        {row.name}
                      </span>
                      <span className="shrink-0 text-xxs tabular-nums text-muted-foreground">
                        {row.whatsapp || "—"}
                      </span>
                    </button>
                  </li>
                )
              })}
            </ul>
          )}
        </ScrollArea>
      </div>
    </div>
  )
}
