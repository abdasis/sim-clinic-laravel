import type { UseFormReturn } from "react-hook-form"
import { z } from "zod"

import { Label } from "#/components/ui/label.tsx"
import { NativeSelect, NativeSelectOption } from "#/components/ui/native-select.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import type { SelectOption } from "#/components/forms/form-select.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { BroadcastImageField } from "./broadcast-image-field.tsx"
import type { BroadcastTemplate } from "./broadcast-types.ts"
import { ContactPicker } from "./contact-picker.tsx"
import { RecipientPreview } from "./recipient-preview.tsx"
import type { AudiencePreview } from "./recipient-preview.tsx"
import { SectionHeader } from "./section-header.tsx"
import {
  BODY_MAX,
  BODY_MAX_WITH_IMAGE,
  TEMPLATE_VARIABLES,
  placeholderNumbers,
  syncParams,
} from "./template-variables.ts"
import { TemplatePreview } from "./template-preview.tsx"

export const broadcastSchema = z.object({
  title: z.string().min(1),
  audience: z.string().min(1),
  days: z.coerce.number().int().gte(1).lte(730).optional(),
  service_id: z.string().optional(),
})

export type BroadcastValues = z.infer<typeof broadcastSchema>

export const BROADCAST_DEFAULTS: BroadcastValues = {
  title: "",
  audience: "all",
  days: 30,
  service_id: "",
}

interface BroadcastFormFieldsProps {
  tenant: string
  form: UseFormReturn<BroadcastValues>
  template: BroadcastTemplate | null
  setTemplate: (template: BroadcastTemplate | null) => void
  image: File | null
  setImage: (file: File | null) => void
  patientIds: number[]
  setPatientIds: (ids: number[]) => void
  previewParams: Record<string, unknown> | null
  previewData?: AudiencePreview
  previewLoading: boolean
  serviceOptions: SelectOption[]
  disabled?: boolean
  /** Gambar header yang sudah tersimpan (halaman ubah). */
  existingImageUrl?: string | null
  removeExistingImage?: boolean
  onRemoveExistingImage?: () => void
}

/**
 * Tiga seksi isian broadcast: detail, penerima, template. Halaman pemanggil
 * yang mengurus judul halaman, panduan, mutasi, dan footer.
 */
export function BroadcastFormFields({
  tenant,
  form,
  template,
  setTemplate,
  image,
  setImage,
  patientIds,
  setPatientIds,
  previewParams,
  previewData,
  previewLoading,
  serviceOptions,
  disabled = false,
  existingImageUrl = null,
  removeExistingImage = false,
  onRemoveExistingImage,
}: BroadcastFormFieldsProps) {
  const { t } = useTrans()
  const audience = form.watch("audience")
  const body = template?.body ?? ""
  const slots = placeholderNumbers(body)
  // Caption yang disertai gambar lebih pendek di WhatsApp, jadi batasnya ikut
  // menyesuaikan poster yang sedang dipakai.
  const imageSelected = Boolean(image) || Boolean(existingImageUrl && !removeExistingImage)
  const bodyLimit = imageSelected ? BODY_MAX_WITH_IMAGE : BODY_MAX

  const changeBody = (next: string) => {
    setTemplate({ body: next, params: syncParams(next, template?.params ?? []) })
  }

  const setParam = (index: number, variable: string) => {
    if (!template) return

    setTemplate({
      ...template,
      params: template.params.map((current, position) =>
        position === index ? variable : current,
      ),
    })
  }

  return (
    <div className="space-y-4">
      <section className="rounded-lg border border-border/50 bg-card p-4 sm:p-5">
        <SectionHeader
          step={1}
          title={t("broadcast.section_details")}
          hint={t("broadcast.section_details_hint")}
        />
        <div className="mt-4">
          <FormInput
            control={form.control}
            name="title"
            label={t("broadcast.title_field")}
            required
            disabled={disabled}
          />
        </div>
      </section>

      <section className="rounded-lg border border-border/50 bg-card p-4 sm:p-5">
        <SectionHeader
          step={2}
          title={t("broadcast.section_audience")}
          hint={t("broadcast.section_audience_hint")}
        />
        <div className="mt-4 space-y-4">
          <FormSelect
            control={form.control}
            name="audience"
            label={t("broadcast.audience_label")}
            disabled={disabled}
            options={[
              { label: t("broadcast.audience.all"), value: "all" },
              { label: t("broadcast.audience.inactive"), value: "inactive" },
              { label: t("broadcast.audience.service"), value: "service" },
              { label: t("broadcast.audience.selected"), value: "selected" },
            ]}
          />

          {audience === "selected" ? (
            <ContactPicker
              tenant={tenant}
              value={patientIds}
              onChange={setPatientIds}
              enabled={!disabled}
            />
          ) : null}

          {audience === "inactive" ? (
            <FormInput
              control={form.control}
              name="days"
              label={t("broadcast.days")}
              type="number"
              min={1}
              max={730}
              required
              disabled={disabled}
              inputClassName="tabular-nums"
            />
          ) : null}

          {audience === "service" ? (
            <FormSelect
              control={form.control}
              name="service_id"
              label={t("service.title")}
              disabled={disabled}
              options={serviceOptions}
            />
          ) : null}

          {/* Daftar penerima tampil sebelum simpan ditekan: broadcast ke
              ratusan orang tidak boleh salah sasaran. */}
          {previewParams !== null ? (
            <RecipientPreview data={previewData} isLoading={previewLoading} />
          ) : null}
        </div>
      </section>

      <section className="rounded-lg border border-border/50 bg-card p-4 sm:p-5">
        <SectionHeader
          step={3}
          title={t("broadcast.section_message")}
          hint={t("broadcast.section_message_hint")}
        />
        <div className="mt-4 space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="broadcast-message-body" className="text-sm font-medium">
              {t("broadcast.message")}
            </Label>
            <Textarea
              id="broadcast-message-body"
              rows={6}
              value={body}
              maxLength={bodyLimit}
              disabled={disabled}
              aria-describedby="broadcast-message-count"
              onChange={(event) => changeBody(event.target.value)}
              className="resize-y"
            />
            <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
              <span className="text-pretty">{t("broadcast.template_body_hint")}</span>
              <span id="broadcast-message-count" className="shrink-0 tabular-nums">
                {body.length} / {bodyLimit}
              </span>
            </div>
          </div>

          {slots.length > 0 ? (
            <div className="grid gap-2 sm:grid-cols-2">
              {slots.map((slot, index) => (
                <div key={slot} className="space-y-1">
                  <Label
                    htmlFor={`broadcast-param-${slot}`}
                    className="text-xs font-medium"
                  >
                    {t("broadcast.template_param_slot").replace(
                      ":number",
                      String(slot),
                    )}
                  </Label>
                  <NativeSelect
                    id={`broadcast-param-${slot}`}
                    className="w-full [&>select]:min-h-11 sm:[&>select]:min-h-10"
                    value={template?.params[index] ?? ""}
                    onChange={(event) => setParam(index, event.target.value)}
                    disabled={disabled}
                    aria-required
                  >
                    <NativeSelectOption value="">—</NativeSelectOption>
                    {TEMPLATE_VARIABLES.map((variable) => (
                      <NativeSelectOption key={variable} value={variable}>
                        {t(`broadcast.template_variable_${variable}`)}
                      </NativeSelectOption>
                    ))}
                  </NativeSelect>
                </div>
              ))}
            </div>
          ) : null}

          <BroadcastImageField
            file={image}
            onChange={setImage}
            existingUrl={existingImageUrl}
            removeExisting={removeExistingImage}
            onRemoveExisting={onRemoveExistingImage}
          />

          <TemplatePreview
            body={body}
            params={template?.params ?? []}
            hasImage={imageSelected}
          />
        </div>
      </section>
    </div>
  )
}
