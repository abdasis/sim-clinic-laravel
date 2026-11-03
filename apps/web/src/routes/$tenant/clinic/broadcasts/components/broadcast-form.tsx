import type { UseFormReturn } from "react-hook-form"
import { z } from "zod"

import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import type { SelectOption } from "#/components/forms/form-select.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { ContactPicker } from "./contact-picker.tsx"
import { RecipientPreview } from "./recipient-preview.tsx"
import type { AudiencePreview } from "./recipient-preview.tsx"
import { SectionHeader } from "./section-header.tsx"
import { TemplateImageField } from "./template-image-field.tsx"
import { TemplatePicker } from "./template-picker.tsx"
import type { BroadcastTemplate } from "./template-picker.tsx"

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

export function templatePayload(template: BroadcastTemplate) {
  const { name, language, body, header_type, params } = template
  return { name, language, body, header_type, params }
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
  onResetExistingImage?: () => void
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
  onResetExistingImage,
}: BroadcastFormFieldsProps) {
  const { t } = useTrans()
  const audience = form.watch("audience")
  const needsImage = template?.header_type === "IMAGE"

  /** Ganti template berarti slot dan berkas gambar lama tidak berlaku lagi. */
  const changeTemplate = (next: BroadcastTemplate | null) => {
    const identityChanged =
      next?.name !== template?.name || next?.language !== template?.language
    setTemplate(next)
    if (identityChanged) {
      setImage(null)
      onResetExistingImage?.()
    }
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
          <TemplatePicker
            tenant={tenant}
            value={template}
            onChange={changeTemplate}
            enabled={!disabled}
            idPrefix="broadcast-template"
          />
          {needsImage ? (
            <TemplateImageField
              file={image}
              onChange={setImage}
              existingUrl={existingImageUrl}
              removeExisting={removeExistingImage}
              onRemoveExisting={onRemoveExistingImage}
              hint={
                image
                  ? t("broadcast.image_hint")
                  : t("broadcast.template_header_image_hint")
              }
            />
          ) : null}
        </div>
      </section>
    </div>
  )
}
