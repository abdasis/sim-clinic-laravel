import { useMutation } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { SectionCard } from "./preference-controls.tsx"

const schema = z
  .object({
    current_password: z.string().min(1),
    password: z.string().min(8),
    password_confirmation: z.string().min(1),
  })
  // Diperiksa di sini juga, bukan hanya di server: salah ketik pada kolom
  // yang isinya tidak terlihat sebaiknya ketahuan sebelum dikirim.
  .refine((values) => values.password === values.password_confirmation, {
    path: ["password_confirmation"],
    message: "confirm",
  })

type Values = z.infer<typeof schema>

const EMPTY: Values = {
  current_password: "",
  password: "",
  password_confirmation: "",
}

/**
 * Ganti kata sandi sendiri.
 *
 * Kata sandi lama tetap diminta walau penggunanya sudah login: sesi yang
 * tertinggal terbuka di komputer klinik cukup untuk mengunci pemiliknya
 * keluar dari akunnya sendiri kalau syarat ini tidak ada.
 */
export function ChangePasswordCard({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const form = useForm(schema, { defaultValues: EMPTY })

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/me/password`, {
        current_password: values.current_password,
        password: values.password,
        password_confirmation: values.password_confirmation,
      }),
    onSuccess: () => {
      toast.success(t("auth.password_changed"))
      // Dikosongkan setelah berhasil: kata sandi tidak boleh tertinggal
      // terbaca di formulir yang mungkin dibiarkan terbuka.
      form.reset(EMPTY)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <SectionCard
      title={t("auth.change_password")}
      description={t("auth.change_password_desc")}
    >
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
          className="grid gap-4 sm:max-w-sm"
        >
          <FormInput
            control={form.control}
            name="current_password"
            type="password"
            autoComplete="current-password"
            label={t("auth.current_password")}
          />
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
          <FormSubmit loading={mutation.isPending}>
            {t("auth.change_password")}
          </FormSubmit>
        </form>
      </Form>
    </SectionCard>
  )
}
