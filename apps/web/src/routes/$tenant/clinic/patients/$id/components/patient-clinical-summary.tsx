import { useMemo } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import type { IconSvgElement } from "@hugeicons/react"
import {
  Alert01Icon,
  Calendar03Icon,
  MedicineBottle01Icon,
  StethoscopeIcon,
} from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import { DASH } from "./record-types.ts"
import type { RecordRow } from "./record-types.ts"

interface PatientClinicalSummaryProps {
  records: RecordRow[]
  productSpend: number
  productCount: number
}

/**
 * Empat angka pembuka riwayat, plus peringatan alergi.
 *
 * Riwayat panjang dibaca dari tabelnya baris per baris; yang hilang justru
 * gambaran cepatnya — sudah berapa kali datang, kapan terakhir, dan seberapa
 * banyak produk yang dipakai di rumah. Semua bisa dihitung dari data yang
 * sudah ada di layar, jadi tidak ada permintaan tambahan ke server.
 */
export function PatientClinicalSummary({
  records,
  productSpend,
  productCount,
}: PatientClinicalSummaryProps) {
  const { t } = useTrans()

  const { visits, lastVisit, treatments, allergies } = useMemo(() => {
    const dates = records
      .map((record) => record.booking?.start_at ?? record.created_at)
      .filter((value): value is string => Boolean(value))
      .sort()

    // Riwayat alergi ditulis ulang tiap kunjungan; yang sama persis tidak
    // diulang sebagai peringatan terpisah.
    const allergies = [
      ...new Set(
        records
          .map((record) => record.allergy_history?.trim())
          .filter((value): value is string => Boolean(value)),
      ),
    ]

    return {
      visits: records.length,
      lastVisit: dates.at(-1) ?? null,
      treatments: records.reduce(
        (sum, record) => sum + (record.treatments?.length ?? 0),
        0,
      ),
      allergies,
    }
  }, [records])

  return (
    <div className="space-y-3">
      {/*
        Alergi didahulukan dan diberi warna peringatan: sebelumnya kolomnya
        tidak muncul sama sekali di layar ini, padahal justru itu yang harus
        terbaca sebelum tindakan apa pun diputuskan.
      */}
      {allergies.length > 0 ? (
        <div
          role="alert"
          className="flex items-start gap-2.5 rounded-lg border border-destructive/40 bg-destructive/5 px-3 py-2.5"
        >
          <HugeiconsIcon
            icon={Alert01Icon}
            strokeWidth={2}
            className="mt-0.5 size-4 shrink-0 text-destructive"
          />
          <div className="min-w-0 space-y-0.5">
            <p className="text-2xs font-semibold tracking-wider text-destructive uppercase">
              {t("medical_record.allergy_alert")}
            </p>
            {allergies.map((allergy) => (
              <p key={allergy} className="text-sm text-pretty">
                {allergy}
              </p>
            ))}
          </div>
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryTile
          icon={Calendar03Icon}
          accent="bg-primary"
          label={t("medical_record.total_visits")}
          value={String(visits)}
          hint={
            lastVisit
              ? `${t("medical_record.last_visit")} ${formatDate(lastVisit)}`
              : DASH
          }
        />
        <SummaryTile
          icon={StethoscopeIcon}
          accent="bg-chart-cat-1"
          label={t("medical_record.total_treatments")}
          value={String(treatments)}
          hint={t("medical_record.total_treatments_hint")}
        />
        <SummaryTile
          icon={MedicineBottle01Icon}
          accent="bg-chart-cat-4"
          label={t("medical_record.products_used")}
          value={String(productCount)}
          hint={t("medical_record.products_used_hint")}
        />
        <SummaryTile
          icon={MedicineBottle01Icon}
          accent="bg-chart-cat-2"
          label={t("medical_record.product_spend")}
          value={formatCurrency(productSpend)}
          hint={t("medical_record.product_spend_hint")}
        />
      </div>
    </div>
  )
}

function SummaryTile({
  icon,
  label,
  value,
  hint,
  accent,
}: {
  icon: IconSvgElement
  label: string
  value: string
  hint: string
  accent: string
}) {
  return (
    <div className="relative overflow-hidden rounded-lg border border-border/60 bg-card p-3.5 transition-colors hover:border-border">
      <span
        aria-hidden
        className={cn("absolute inset-y-0 left-0 w-[3px]", accent)}
      />
      <div className="flex items-center gap-2 pl-1.5">
        <span
          aria-hidden
          className="flex size-6 shrink-0 items-center justify-center rounded-md bg-muted"
        >
          <HugeiconsIcon
            icon={icon}
            strokeWidth={2}
            className="size-3.5 text-muted-foreground"
          />
        </span>
        <p className="text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
          {label}
        </p>
      </div>
      <p className="mt-1.5 pl-1.5 text-xl leading-tight font-semibold tabular-nums">
        {value}
      </p>
      <p className="mt-0.5 truncate pl-1.5 text-xs text-muted-foreground">
        {hint}
      </p>
    </div>
  )
}
