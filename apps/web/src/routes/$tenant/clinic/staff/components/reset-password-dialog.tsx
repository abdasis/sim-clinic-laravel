import { useEffect } from "react"
import { useMutation } from "@tanstack/react-query"
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
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z
  .object({
    password: z.string().min(8),
    password_confirmation: z.string().min(1),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ["password_confirmation"],
    message: "confirm",
  })

type Values = z.infer<typeof schema>

const EMPTY: Values = { password: "", password_confirmation: "" }

interface ResetPasswordDialogProps {
  tenant: string
  staffId: number
  staffName: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Admin menyetel ulang kata sandi seorang staf.
 *
 * Klinik tidak punya jalur lupa-kata-sandi lewat email, jadi tanpa ini
 * terapis yang lupa kata sandinya terkunci selamanya — dan satu-satunya
 * jalan keluar adalah membuat akun baru, yang memutus riwayat kerjanya.
 *
 * Kata sandi lama tidak diminta: admin memang tidak mengetahuinya, dan
 * justru itu keadaan yang membuat penyetelan ulang dibutuhkan. Akibatnya
 * disebut terang-terangan di keterangan dialog — staf itu akan keluar dari
 * semua perangkatnya.
 */
export function ResetPasswordDialog({
  tenant,
  staffId,
  staffName,
  open,
  onOpenChange,
}: ResetPasswordDialogProps) {
  const { t } = useTrans()
  const form = useForm(schema, { defaultValues: EMPTY })

  // Dikosongkan tiap kali dibuka: kata sandi yang sempat diketik untuk staf
  // lain tidak boleh tertinggal di formulir yang sama.
  useEffect(() => {
    if (open) form.reset(EMPTY)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/staff/${staffId}/password`, {
        password: values.password,
        password_confirmation: values.password_confirmation,
      }),
    onSuccess: () => {
      toast.success(t("auth.staff_password_reset"))
      form.reset(EMPTY)
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>
            {t("auth.reset_password")} — {staffName}
          </DialogTitle>
          <DialogDescription>{t("auth.reset_password_desc")}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="grid gap-4"
          >
            <FormInput
              control={form.control}
              name="password"
              type="password"
              autoComplete="new-password"
              label={t("auth.new_password")}
              description={t("auth.password_min_hint")}
            />
            <FormInput
              control={form.control}
              name="password_confirmation"
              type="password"
              autoComplete="new-password"
              label={t("auth.confirm_password")}
            />
            <DialogFooter>
              <FormSubmit loading={mutation.isPending}>
                {t("auth.reset_password")}
              </FormSubmit>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
