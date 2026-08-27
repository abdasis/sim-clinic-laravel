import { useEffect, useRef, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useNavigate } from "@tanstack/react-router"
import { z } from "zod"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Delete02Icon, ImageUpload01Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost, apiUpload } from "#/lib/api.ts"
import { ContactPicker } from "./contact-picker.tsx"
import { RecipientPreview } from "./recipient-preview.tsx"
import type { AudiencePreview } from "./recipient-preview.tsx"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  title: z.string().min(1),
  message: z.string().min(1).max(4000),
  audience: z.string().min(1),
  days: z.coerce.number().int().gte(1).lte(730).optional(),
  service_id: z.string().optional(),
})

type Values = z.infer<typeof schema>

/** Variabel yang dirender server ke tiap pesan. */
const VARIABLES = [
  "{nama}",
  "{klinik}",
  "{layanan_terakhir}",
  "{tanggal_terakhir}",
  "{hari_sejak_kunjungan}",
] as const

const EMPTY: Values = {
  title: "",
  message: "",
  audience: "all",
  days: 30,
  service_id: "",
}

interface BroadcastFormDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function BroadcastFormDialog({
  tenant,
  open,
  onOpenChange,
}: BroadcastFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const navigate = useNavigate()

  const form = useForm(schema, { defaultValues: EMPTY })
  const audience = form.watch("audience")
  const days = form.watch("days")
  const serviceId = form.watch("service_id")

  const [previewParams, setPreviewParams] = useState<Record<string, unknown> | null>(null)
  const [patientIds, setPatientIds] = useState<number[]>([])

  // Berkas tidak masuk react-hook-form: yang dikirim ke server bukan nilai
  // teks melainkan FormData, dan schema zod-nya tidak punya kepentingan di
  // sana selain memvalidasi ulang apa yang sudah dijaga input file.
  const [image, setImage] = useState<File | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const fileInput = useRef<HTMLInputElement>(null)

  const clearImage = () => {
    setImage(null)
    setImagePreview((url) => {
      if (url) URL.revokeObjectURL(url)
      return null
    })
    if (fileInput.current) fileInput.current.value = ""
  }

  // Hitung penerima hanya saat pilihannya lengkap; select layanan yang masih
  // kosong tidak perlu menembak server.
  useEffect(() => {
    if (!open) return

    if (audience === "inactive" && !days) return setPreviewParams(null)
    if (audience === "service" && !serviceId) return setPreviewParams(null)
    // Belum ada kontak yang dipilih: tidak ada yang perlu dihitung, dan
    // menembak server hanya untuk dijawab nol tidak menolong siapa pun.
    if (audience === "selected" && patientIds.length === 0) {
      return setPreviewParams(null)
    }

    setPreviewParams({
      audience,
      days: audience === "inactive" ? days : undefined,
      service_id: audience === "service" ? serviceId : undefined,
      patient_ids: audience === "selected" ? patientIds : undefined,
    })
  }, [open, audience, days, serviceId, patientIds])

  const preview = useQuery({
    queryKey: ["broadcast-preview", tenant, previewParams],
    queryFn: () =>
      apiGet<{ data: AudiencePreview }>(
        `/${tenant}/clinic/broadcasts/audience-preview`,
        previewParams ?? {},
      ),
    enabled: open && previewParams !== null,
  })

  const templates = useQuery({
    queryKey: ["message-templates", tenant],
    queryFn: () =>
      apiGet<{ data: { id: number; name: string; body: string }[] }>(
        `/${tenant}/clinic/message-templates`,
      ),
    enabled: open,
  })

  const services = useQuery({
    queryKey: ["services", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: { id: number; name: string }[] }>(
        `/${tenant}/clinic/services`,
        { per_page: 200 },
      ),
    enabled: open && audience === "service",
  })

  useEffect(() => {
    if (open) {
      form.reset(EMPTY)
      clearImage()
      setPatientIds([])
    }
    // clearImage sengaja tidak jadi dependensi: ia dibuat ulang tiap render
    // dan hanya menyentuh state lokal, jadi menambahkannya justru membuat
    // efek ini berjalan terus-menerus.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, form])

  // Object URL menahan berkasnya di memori sampai dilepas.
  useEffect(() => () => {
    if (imagePreview) URL.revokeObjectURL(imagePreview)
  }, [imagePreview])

  const mutation = useMutation({
    mutationFn: (values: Values) => {
      const days = values.audience === "inactive" ? values.days : undefined
      const serviceId =
        values.audience === "service" && values.service_id
          ? Number(values.service_id)
          : undefined
      const selected = values.audience === "selected" ? patientIds : undefined

      if (!image) {
        return apiPost<{ data: { id: number } }>(`/${tenant}/clinic/broadcasts`, {
          title: values.title,
          message: values.message,
          audience: values.audience,
          audience_params: { days, service_id: serviceId, patient_ids: selected },
        })
      }

      // Berkas tidak muat di JSON, jadi seluruh muatannya pindah ke
      // FormData. Kunci bersarang ditulis manual karena FormData tidak
      // mengenal objek — bentuknya harus sama dengan yang dibaca server.
      const fd = new FormData()
      fd.append("title", values.title)
      fd.append("message", values.message)
      fd.append("audience", values.audience)
      fd.append("image", image)
      if (days !== undefined) fd.append("audience_params[days]", String(days))
      if (serviceId !== undefined) {
        fd.append("audience_params[service_id]", String(serviceId))
      }
      // FormData tidak mengenal larik, jadi tiap id ditulis dengan kurung
      // siku kosong — bentuk yang dibaca PHP sebagai array.
      selected?.forEach((id) => {
        fd.append("audience_params[patient_ids][]", String(id))
      })

      return apiUpload<{ data: { id: number } }>(`/${tenant}/clinic/broadcasts`, fd)
    },
    onSuccess: (res) => {
      toast.success(t("broadcast.created"))
      qc.invalidateQueries({ queryKey: ["broadcasts"] })
      onOpenChange(false)
      // Langsung ke antrean penerimanya — di sanalah pengirimannya terjadi.
      navigate({
        to: "/$tenant/clinic/broadcasts/$id",
        params: { tenant, id: String(res.data.id) },
      })
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  const insertVariable = (variable: string) => {
    form.setValue("message", `${form.getValues("message")}${variable}`, {
      shouldValidate: true,
    })
  }

  const applyTemplate = (key: "template_reminder" | "template_promo") => {
    form.setValue("message", t(`broadcast.${key}`), { shouldValidate: true })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90dvh] gap-0 overflow-hidden p-0 sm:max-w-lg">
        <DialogHeader className="border-b border-border/50 p-4">
          <DialogTitle>{t("broadcast.add")}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="flex min-h-0 flex-col"
          >
            {/*
              Wadah gulir CSS biasa, bukan ScrollArea Radix: viewport-nya
              memakai height 100% terhadap induk yang tingginya auto, jadi
              max-height di root tidak mengekang apa pun dan isinya meluber
              menimpa seluruh dialog begitu daftarnya panjang.
            */}
            <div className="max-h-[62dvh] min-h-0 flex-1 overflow-y-auto">
              <div className="space-y-4 p-4">
                <FormInput
                  control={form.control}
                  name="title"
                  label={t("broadcast.title_field")}
                  required
                />

                <FormSelect
                  control={form.control}
                  name="audience"
                  label={t("broadcast.audience_label")}
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
                    enabled={open}
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
                    inputClassName="tabular-nums"
                  />
                ) : null}

                {audience === "service" ? (
                  <FormSelect
                    control={form.control}
                    name="service_id"
                    label={t("service.title")}
                    options={(services.data?.data ?? []).map((service) => ({
                      label: service.name,
                      value: String(service.id),
                    }))}
                  />
                ) : null}

                {/* Daftar penerimanya tampil sebelum tombol simpan ditekan,
                    bukan sekadar jumlahnya — broadcast ke 900 orang tidak
                    boleh terjadi karena salah pilih sasaran, dan angka saja
                    tidak cukup untuk menyadarinya. */}
                {previewParams !== null ? (
                  <RecipientPreview
                    data={preview.data?.data}
                    isLoading={preview.isLoading}
                  />
                ) : null}

                <div className="space-y-2">
                  <div className="flex flex-wrap gap-1.5">
                    {(templates.data?.data ?? []).map((template) => (
                      <Button
                        key={template.id}
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() =>
                          form.setValue("message", template.body, {
                            shouldValidate: true,
                          })
                        }
                      >
                        {template.name}
                      </Button>
                    ))}
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="h-7 text-xs"
                      onClick={() => applyTemplate("template_reminder")}
                    >
                      {t("broadcast.audience.inactive")}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="h-7 text-xs"
                      onClick={() => applyTemplate("template_promo")}
                    >
                      {t("promo.title")}
                    </Button>
                  </div>

                  <FormTextarea
                    control={form.control}
                    name="message"
                    label={t("broadcast.message")}
                  />

                  {/* Poster promo. Ditaruh setelah pesan karena ia yang
                      jadi keterangan gambarnya — urutan di layar mengikuti
                      urutan yang diterima pasien. */}
                  <div className="space-y-1.5">
                    <label
                      htmlFor="broadcast-image"
                      className="text-sm font-medium"
                    >
                      {t("broadcast.image")}
                    </label>

                    {imagePreview ? (
                      <div className="flex items-center gap-3 rounded-md border border-border/60 bg-muted/30 p-2">
                        <img
                          src={imagePreview}
                          alt={image?.name ?? ""}
                          className="size-14 shrink-0 rounded object-cover"
                        />
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">{image?.name}</p>
                          <p className="text-xs text-muted-foreground tabular-nums">
                            {Math.round((image?.size ?? 0) / 1024)} KB
                          </p>
                        </div>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button
                              type="button"
                              size="icon"
                              variant="ghost"
                              className="shrink-0 text-muted-foreground hover:text-destructive"
                              aria-label={t("broadcast.image_remove")}
                              onClick={clearImage}
                            >
                              <HugeiconsIcon
                                icon={Delete02Icon}
                                strokeWidth={2}
                                className="size-4"
                              />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>{t("broadcast.image_remove")}</TooltipContent>
                        </Tooltip>
                      </div>
                    ) : (
                      <button
                        type="button"
                        onClick={() => fileInput.current?.click()}
                        className="flex w-full items-center gap-2 rounded-md border border-dashed border-border/70 bg-muted/20 px-3 py-3 text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted/40 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                      >
                        <HugeiconsIcon
                          icon={ImageUpload01Icon}
                          strokeWidth={2}
                          className="size-4 shrink-0"
                        />
                        {t("broadcast.image")}
                      </button>
                    )}

                    <input
                      id="broadcast-image"
                      ref={fileInput}
                      type="file"
                      accept="image/jpeg,image/png"
                      className="sr-only"
                      onChange={(event) => {
                        const file = event.target.files?.[0] ?? null
                        clearImage()
                        if (!file) return
                        setImage(file)
                        setImagePreview(URL.createObjectURL(file))
                      }}
                    />

                    <p className="text-xs text-muted-foreground">
                      {t("broadcast.image_hint")}
                    </p>
                  </div>

                  <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-xs text-muted-foreground">
                      {t("broadcast.variables_hint")}
                    </span>
                    {VARIABLES.map((variable) => (
                      <Tooltip key={variable}>
                        <TooltipTrigger asChild>
                          <button
                            type="button"
                            onClick={() => insertVariable(variable)}
                            className="focus-visible:ring-ring/50 rounded-sm outline-none focus-visible:ring-2"
                          >
                            <Badge
                              variant="secondary"
                              className="cursor-pointer font-mono text-xxs font-normal transition-colors hover:bg-primary/15"
                            >
                              {variable}
                            </Badge>
                          </button>
                        </TooltipTrigger>
                        <TooltipContent>{variable}</TooltipContent>
                      </Tooltip>
                    ))}
                  </div>
                </div>
              </div>
            </div>

            <DialogFooter className="border-t border-border/50 p-4">
              <FormSubmit loading={mutation.isPending}>
                {t("general.save")}
              </FormSubmit>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
