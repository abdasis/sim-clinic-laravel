import { useEffect, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useNavigate } from "@tanstack/react-router"
import { z } from "zod"
import { toast } from "sonner"

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
import { ScrollArea } from "#/components/ui/scroll-area.tsx"
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
import { apiGet, apiPost } from "#/lib/api.ts"
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

  // Hitung penerima hanya saat pilihannya lengkap; select layanan yang masih
  // kosong tidak perlu menembak server.
  useEffect(() => {
    if (!open) return

    if (audience === "inactive" && !days) return setPreviewParams(null)
    if (audience === "service" && !serviceId) return setPreviewParams(null)

    setPreviewParams({
      audience,
      days: audience === "inactive" ? days : undefined,
      service_id: audience === "service" ? serviceId : undefined,
    })
  }, [open, audience, days, serviceId])

  const preview = useQuery({
    queryKey: ["broadcast-preview", tenant, previewParams],
    queryFn: () =>
      apiGet<{ data: { count: number; without_phone: number } }>(
        `/${tenant}/clinic/broadcasts/audience-preview`,
        previewParams ?? {},
      ),
    enabled: open && previewParams !== null,
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
    if (open) form.reset(EMPTY)
  }, [open, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost<{ data: { id: number } }>(`/${tenant}/clinic/broadcasts`, {
        title: values.title,
        message: values.message,
        audience: values.audience,
        audience_params: {
          days: values.audience === "inactive" ? values.days : undefined,
          service_id:
            values.audience === "service" && values.service_id
              ? Number(values.service_id)
              : undefined,
        },
      }),
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
            <ScrollArea className="max-h-[62dvh] min-h-0 flex-1">
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
                  ]}
                />

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

                {/* Jumlah penerima tampil sebelum tombol simpan ditekan —
                    broadcast ke 900 orang tidak boleh terjadi karena salah pilih. */}
                {preview.data ? (
                  <div className="rounded-md border border-border/50 bg-muted/40 px-3 py-2 text-xs">
                    <p className="font-medium">
                      {t("broadcast.preview_count").replace(
                        ":count",
                        String(preview.data.data.count),
                      )}
                    </p>
                    {preview.data.data.without_phone > 0 ? (
                      <p className="mt-0.5 text-muted-foreground">
                        {t("broadcast.without_phone").replace(
                          ":count",
                          String(preview.data.data.without_phone),
                        )}
                      </p>
                    ) : null}
                  </div>
                ) : null}

                <div className="space-y-2">
                  <div className="flex flex-wrap gap-1.5">
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
            </ScrollArea>

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
