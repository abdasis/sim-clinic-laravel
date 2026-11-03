import { Fragment } from "react"
import { useQuery } from "@tanstack/react-query"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { NativeSelect, NativeSelectOption } from "#/components/ui/native-select.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiErrorMessage, apiGet } from "#/lib/api.ts"

export type TemplateHeaderType = "IMAGE" | "TEXT" | "DOCUMENT" | "VIDEO"

/** Template siap kirim: slot {{n}} sudah dipetakan ke variabel penerima. */
export interface BroadcastTemplate {
  name: string
  language: string
  body: string
  header_type: TemplateHeaderType | null
  params: string[]
}

/** Bentuk template dari Meta; `body_params_count` menentukan jumlah slot. */
export interface CloudTemplate {
  name: string
  language: string
  category: string
  body: string
  header_type: TemplateHeaderType | null
  body_params_count: number
}

export const TEMPLATE_VARIABLES = [
  "nama",
  "klinik",
  "klinik_telepon",
  "layanan_terakhir",
  "tanggal_terakhir",
  "hari_sejak_kunjungan",
] as const

const DEFAULT_PARAMS = [
  "nama",
  "klinik",
  "layanan_terakhir",
  "tanggal_terakhir",
  "hari_sejak_kunjungan",
]

const CHIP = /^\{\{\s*\d+\s*\}\}$/

export function isTemplateComplete(template: BroadcastTemplate | null): boolean {
  return template !== null && template.params.every((value) => value !== "")
}

function keyOf(template: { name: string; language: string }): string {
  return `${template.name}::${template.language}`
}

interface TemplatePickerProps {
  tenant: string
  value: BroadcastTemplate | null
  onChange: (template: BroadcastTemplate | null) => void
  enabled?: boolean
  label?: string
  hint?: string
  /** Tampilkan petunjuk gambar saat header template bertipe IMAGE. */
  showImageHint?: boolean
  idPrefix?: string
}

/**
 * Pemilih template resmi Meta beserta pemetaan tiap slot {{n}} ke data
 * penerima. Jumlah slot mengikuti `body_params_count`, bukan tebakan dari
 * isi body.
 */
export function TemplatePicker({
  tenant,
  value,
  onChange,
  enabled = true,
  label,
  hint,
  showImageHint = false,
  idPrefix = "broadcast-template",
}: TemplatePickerProps) {
  const { t } = useTrans()

  const query = useQuery({
    queryKey: ["broadcast-templates", tenant],
    queryFn: () =>
      apiGet<{ data: CloudTemplate[]; meta?: { ready?: boolean } }>(
        `/${tenant}/clinic/broadcasts/templates`,
      ),
    enabled,
  })

  // Jawaban non-larik tidak boleh menjatuhkan dialog: pengaturan memakai
  // komponen ini juga, dan server bisa menjawab galat dalam bentuk lain.
  const templates = Array.isArray(query.data?.data) ? query.data.data : []
  const ready = query.data?.meta?.ready !== false
  const selectedKey = value ? keyOf(value) : ""
  const selected = templates.find((item) => keyOf(item) === selectedKey)

  const choose = (key: string) => {
    if (key === "") return onChange(null)

    const template = templates.find((item) => keyOf(item) === key)
    if (!template) return

    const same = value !== null && keyOf(value) === key
    onChange({
      name: template.name,
      language: template.language,
      body: template.body,
      header_type: template.header_type,
      params: Array.from({ length: template.body_params_count }, (_, index) =>
        same ? (value?.params[index] ?? DEFAULT_PARAMS[index] ?? "") : (DEFAULT_PARAMS[index] ?? ""),
      ),
    })
  }

  const setParam = (index: number, variable: string) => {
    if (!value) return
    onChange({
      ...value,
      params: value.params.map((current, position) =>
        position === index ? variable : current,
      ),
    })
  }

  if (query.isLoading) {
    return (
      <div className="space-y-1.5">
        <Skeleton className="h-4 w-32" />
        <Skeleton className="h-8 w-full" />
        <p className="text-xs text-muted-foreground">{t("broadcast.template_loading")}</p>
      </div>
    )
  }

  if (query.isError) {
    return (
      <div className="space-y-1.5">
        <p className="text-xs text-destructive">
          {apiErrorMessage(query.error) ?? t("general.error")}
        </p>
        <Button
          type="button"
          size="sm"
          variant="outline"
          onClick={() => void query.refetch()}
          disabled={query.isFetching}
        >
          {t("general.retry")}
        </Button>
      </div>
    )
  }

  if (!ready) {
    return (
      <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-pretty text-amber-700 dark:text-amber-400">
        {t("broadcast.template_not_ready")}
      </p>
    )
  }

  return (
    <div className="space-y-2">
      <div className="space-y-1.5">
        <label htmlFor={`${idPrefix}-select`} className="text-sm font-medium">
          {label ?? t("broadcast.template_label")}
        </label>
        <NativeSelect
          id={`${idPrefix}-select`}
          className="w-full [&>select]:min-h-11 sm:[&>select]:min-h-10"
          value={selectedKey}
          onChange={(event) => choose(event.target.value)}
          disabled={!enabled}
        >
          <NativeSelectOption value="">—</NativeSelectOption>
          {value && !selected ? (
            <NativeSelectOption value={selectedKey}>
              {value.name} ({value.language})
            </NativeSelectOption>
          ) : null}
          {templates.map((template) => (
            <NativeSelectOption key={keyOf(template)} value={keyOf(template)}>
              {template.name} · {template.language}
            </NativeSelectOption>
          ))}
        </NativeSelect>
        <p className="text-xs text-muted-foreground">
          {hint ?? t("broadcast.template_hint")}
        </p>
        {templates.length === 0 ? (
          <p className="text-xs text-muted-foreground">{t("broadcast.no_templates")}</p>
        ) : null}
      </div>

      {value ? (
        <div className="space-y-2 rounded-md border border-border/50 bg-muted/30 p-3">
          <div className="flex flex-wrap items-center gap-1.5">
            <Badge variant="secondary" className="font-normal">
              {value.name} · {value.language}
            </Badge>
            {selected?.category ? (
              <Badge variant="outline" className="font-normal">
                {selected.category}
              </Badge>
            ) : null}
          </div>

          <div className="space-y-1">
            <p className="text-xs font-medium">{t("broadcast.template_preview")}</p>
            <p className="rounded-sm border border-border/50 bg-background px-2.5 py-2 text-xs leading-relaxed whitespace-pre-wrap text-pretty">
              {value.body.split(/(\{\{\s*\d+\s*\}\})/g).map((part, index) =>
                CHIP.test(part) ? (
                  <span
                    key={`${index}-${part}`}
                    className="mx-0.5 inline-flex items-center rounded-sm bg-primary/10 px-1 font-mono text-xxs text-primary"
                  >
                    {part.replace(/\s+/g, "")}
                  </span>
                ) : (
                  <Fragment key={`${index}-${part}`}>{part}</Fragment>
                ),
              )}
            </p>
          </div>

          {value.params.length > 0 ? (
            <div className="grid gap-2 sm:grid-cols-2">
              {value.params.map((variable, index) => (
                <div key={`${value.name}-${value.language}-${index}`} className="space-y-1">
                  <label
                    htmlFor={`${idPrefix}-param-${index}`}
                    className="text-xs font-medium"
                  >
                    {t("broadcast.template_param_slot").replace(":number", String(index + 1))}
                  </label>
                  <NativeSelect
                    id={`${idPrefix}-param-${index}`}
                    className="w-full [&>select]:min-h-11 sm:[&>select]:min-h-10"
                    value={variable}
                    onChange={(event) => setParam(index, event.target.value)}
                    disabled={!enabled}
                    aria-required
                    aria-invalid={variable === ""}
                  >
                    <NativeSelectOption value="">—</NativeSelectOption>
                    {TEMPLATE_VARIABLES.map((option) => (
                      <NativeSelectOption key={option} value={option}>
                        {t(`broadcast.template_variable_${option}`)}
                      </NativeSelectOption>
                    ))}
                  </NativeSelect>
                </div>
              ))}
            </div>
          ) : null}

          {value.header_type === "IMAGE" && showImageHint ? (
            <p className="text-xxs text-muted-foreground text-pretty">
              {t("broadcast.template_header_image_hint")}
            </p>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
