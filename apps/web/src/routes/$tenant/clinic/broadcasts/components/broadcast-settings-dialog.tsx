import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { ConnectionState } from "./whatsapp-tools.tsx"

/**
 * Pesannya diterjemahkan saat schema dibuat, bukan saat dirender: zod
 * menyimpan string jadi, bukan kunci.
 */
function buildSchema(sessionFormat: string) {
  return z.object({
    // Boleh kosong: klinik yang belum punya sesi tetap bisa kirim manual.
    session: z.string().max(64).regex(/^[a-z0-9-]*$/, sessionFormat).optional(),
  })
}

type Values = z.infer<ReturnType<typeof buildSchema>>

interface Settings {
  session?: string | null
  daily_message_limit?: number | null
  gateway_available: boolean
}

interface BroadcastSettingsDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Yang diatur klinik: nama sesi gateway. Alamat server milik pengelola
 * platform, diisi di halaman central. Nama sesi dikunci begitu sesinya dibuat
 * di gateway — menggantinya diam-diam meninggalkan sesi lama yang masih
 * tertaut nomor klinik.
 */
export function BroadcastSettingsDialog({
  tenant,
  open,
  onOpenChange,
}: BroadcastSettingsDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const settings = useQuery({
    queryKey: ["broadcast-settings", tenant],
    queryFn: () =>
      apiGet<{ data: Settings }>(`/${tenant}/clinic/broadcasts/settings`),
    enabled: open,
  })

  // Keadaan sesi menentukan apakah namanya masih boleh diubah. Dibaca dari
  // endpoint yang sama dengan dialog Koneksi, jadi cache-nya ikut terpakai.
  const connection = useQuery({
    queryKey: ["wa-connection", tenant],
    queryFn: () =>
      apiGet<{ data: ConnectionState }>(`/${tenant}/clinic/broadcasts/connection`),
    enabled: open,
  })

  // Dibangun ulang tiap render: `t` baru bisa menjawab setelah terjemahan
  // termuat, dan react-hook-form membaca resolver terbaru saat memvalidasi.
  const form = useForm(buildSchema(t("broadcast.session_format")), {
    defaultValues: { session: "" },
  })

  const current = settings.data?.data
  const provisioned = connection.data?.data.provisioned === true

  useEffect(() => {
    if (!open || !current) return

    form.reset({ session: current.session ?? "" })
  }, [open, current, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/broadcasts/settings`, {
        session: values.session?.trim() || null,
      }),
    onSuccess: () => {
      toast.success(t("broadcast.settings_saved"))
      qc.invalidateQueries({ queryKey: ["broadcast-settings"] })
      qc.invalidateQueries({ queryKey: ["broadcasts"] })
      qc.invalidateQueries({ queryKey: ["wa-connection"] })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      {/* Dialog ini hanya punya satu field. `layout="form"` membuatnya setinggi
          layar di ponsel, menyisakan ruang kosong besar; `h-fit` mengembalikan
          tinggi ke isi tanpa melepas perilaku formulir (footer menempel). */}
      <DialogContent layout="form" className="max-md:h-fit">
        <DialogHeader>
          <DialogTitle>{t("broadcast.settings")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("broadcast.session_hint")}
          </DialogDescription>
        </DialogHeader>

        {settings.isLoading ? (
          <Skeleton className="h-16 w-full" />
        ) : (
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
              className="flex min-h-0 flex-1 flex-col gap-4"
            >
              <DialogBody className="space-y-4">
                {current && !current.gateway_available ? (
                  <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-pretty text-amber-700 dark:text-amber-400">
                    {t("broadcast.gateway_server_unconfigured")}
                  </p>
                ) : null}

                <FormInput
                  control={form.control}
                  name="session"
                  label={t("broadcast.session_label")}
                  placeholder="klinik-satu"
                  description={
                    provisioned
                      ? t("broadcast.session_locked")
                      : t("broadcast.session_empty")
                  }
                  disabled={provisioned}
                  inputClassName="font-mono"
                />
              </DialogBody>
              <DialogFooter>
                <FormSubmit loading={mutation.isPending} disabled={provisioned}>
                  {t("general.save")}
                </FormSubmit>
              </DialogFooter>
            </form>
          </Form>
        )}
      </DialogContent>
    </Dialog>
  )
}
