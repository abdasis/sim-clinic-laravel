import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import {
  Dialog,
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

/**
 * Pesannya diterjemahkan saat schema dibuat, bukan saat dirender: zod
 * menyimpan string jadi, bukan kunci.
 */
function buildSchema(formatMessage: string) {
  return z.object({
    // Boleh kosong: klinik yang belum pakai WAHA tetap bisa kirim manual.
    session: z
      .string()
      .max(100)
      .regex(/^[a-z0-9-]*$/, formatMessage)
      .optional(),
  })
}

type Values = z.infer<ReturnType<typeof buildSchema>>

interface BroadcastSettingsDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Satu-satunya yang diatur klinik: nama sesi WAHA miliknya. Alamat server dan
 * API key-nya milik pengelola platform, diisi di halaman central.
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
      apiGet<{
        data: {
          session?: string | null
          waha_available: boolean
        }
      }>(`/${tenant}/clinic/broadcasts/settings`),
    enabled: open,
  })

  // Dibangun ulang tiap render: `t` baru bisa menjawab setelah terjemahan
  // termuat, dan react-hook-form membaca resolver terbaru saat memvalidasi.
  const form = useForm(buildSchema(t("broadcast.session_format")), {
    defaultValues: { session: "" },
  })

  const current = settings.data?.data

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
      <DialogContent>
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
              className="space-y-4"
            >
              {current && !current.waha_available ? (
                <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-pretty text-amber-700 dark:text-amber-400">
                  {t("broadcast.waha_not_configured")}
                </p>
              ) : null}

              <FormInput
                control={form.control}
                name="session"
                label={t("broadcast.session_label")}
                placeholder="klinik-satu"
                description={t("broadcast.session_empty")}
                inputClassName="font-mono"
              />

              <DialogFooter>
                <FormSubmit loading={mutation.isPending}>
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
