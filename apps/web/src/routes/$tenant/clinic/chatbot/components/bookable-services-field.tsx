import { useMemo, useState } from "react"
import { useQuery } from "@tanstack/react-query"

import { Checkbox } from "#/components/ui/checkbox.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { Label } from "#/components/ui/label.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency } from "#/lib/format.ts"

interface ServiceOption {
  id: number
  name: string
  price: number | string
}

/**
 * Pemilih layanan yang boleh dibooking lewat chat.
 *
 * Daftar centang, bukan multi-select: klinik perlu melihat seluruh katalognya
 * sekaligus untuk memutuskan mana yang aman dilepas ke chatbot, dan harga ikut
 * ditampilkan karena itu yang membedakan treatment ringan dari tindakan yang
 * sebaiknya dijadwalkan lewat orang.
 *
 * Tidak ada yang dicentang berarti seluruh layanan boleh — sengaja disebut
 * terang-terangan di layar supaya tidak terbaca sebagai "belum diatur".
 */
export function BookableServicesField({
  tenant,
  value,
  onChange,
}: {
  tenant: string
  value: number[]
  onChange: (next: number[]) => void
}) {
  const { t } = useTrans()
  const [keyword, setKeyword] = useState("")

  const { data, isLoading } = useQuery({
    queryKey: ["services", tenant, "chatbot-options"],
    queryFn: () =>
      apiGet<{ data: ServiceOption[] }>(`/${tenant}/clinic/services`, {
        per_page: 200,
        filter: { status: "active" },
      }),
  })

  const services = data?.data ?? []

  const shown = useMemo(() => {
    const needle = keyword.trim().toLowerCase()

    return needle === ""
      ? services
      : services.filter((service) => service.name.toLowerCase().includes(needle))
  }, [services, keyword])

  const toggle = (id: number) =>
    onChange(value.includes(id) ? value.filter((item) => item !== id) : [...value, id])

  return (
    <div className="space-y-2">
      <Label>{t("chatbot.bookable_services")}</Label>

      <div className="overflow-hidden rounded-md border border-border/60">
        <div className="flex items-center gap-2 border-b border-border/60 bg-muted/30 p-2">
          <Input
            value={keyword}
            onChange={(event) => setKeyword(event.target.value)}
            placeholder={t("general.search")}
            className="h-8"
          />
          <button
            type="button"
            className="shrink-0 rounded-md px-2 py-1 text-xs text-muted-foreground transition-colors duration-150 hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
            disabled={value.length === 0}
            onClick={() => onChange([])}
          >
            {t("chatbot.clear_selection")}
          </button>
        </div>

        <div className="max-h-64 overflow-y-auto">
          {isLoading ? (
            <div className="space-y-2 p-2">
              {[0, 1, 2].map((row) => (
                <Skeleton key={row} className="h-7 w-full" />
              ))}
            </div>
          ) : shown.length === 0 ? (
            <p className="p-4 text-center text-xs text-muted-foreground">
              {t("chatbot.no_service_found")}
            </p>
          ) : (
            shown.map((service) => (
              <label
                key={service.id}
                className="flex cursor-pointer items-center gap-2.5 border-b border-border/40 px-3 py-2 text-sm transition-colors duration-100 last:border-b-0 hover:bg-muted/40"
              >
                <Checkbox
                  checked={value.includes(service.id)}
                  onCheckedChange={() => toggle(service.id)}
                />
                <span className="min-w-0 flex-1 truncate">{service.name}</span>
                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                  {formatCurrency(Number(service.price))}
                </span>
              </label>
            ))
          )}
        </div>
      </div>

      <p className="text-xs text-muted-foreground">
        {value.length === 0
          ? t("chatbot.bookable_services_hint")
          : `${value.length} ${t("chatbot.services_selected")}`}
      </p>
    </div>
  )
}
