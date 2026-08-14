import { useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent } from "#/components/ui/card.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { ContentField } from "./content-field.tsx"
import type { EntitySchema } from "./entity-schema.ts"

interface ContentFormProps {
  tenant: string
  schema: EntitySchema
  /** Ada isinya = mode ubah. */
  recordId?: number
  initialValues: Record<string, unknown>
}

/**
 * Form CMS yang bentuknya mengikuti skema entitas. Satu komponen melayani
 * delapan entitas — perbedaannya hanya daftar field.
 */
export function ContentForm({
  tenant,
  schema,
  recordId,
  initialValues,
}: ContentFormProps) {
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [values, setValues] = useState(initialValues)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const base = `/${tenant}/clinic/company-profile/${schema.slug}`

  const save = useMutation({
    mutationFn: () =>
      recordId
        ? apiPut(`${base}/${recordId}`, values)
        : apiPost(base, values),
    onSuccess: () => {
      toast.success(
        recordId ? t("company_profile.updated") : t("company_profile.created"),
      )
      qc.invalidateQueries({ queryKey: ["company-content", tenant, schema.slug] })
      navigate({
        to: "/$tenant/clinic/company-profile/$entity",
        params: { tenant, entity: schema.slug },
      })
    },
    onError: (err: ApiError) => {
      // Error validasi peta bahasa datang sebagai "title.id"; tampilkan di
      // field induknya supaya pesannya kelihatan.
      const mapped: Record<string, string> = {}

      for (const [key, messages] of Object.entries(err.errors ?? {})) {
        mapped[key.split(".")[0]] = messages[0]
      }

      setErrors(mapped)
      toast.error(err.message)
    },
  })

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        setErrors({})
        save.mutate()
      }}
      className="space-y-4"
    >
      <Card>
        <CardContent className="grid gap-4 p-5 sm:grid-cols-2">
          {schema.fields.map((field) => (
            <div
              key={field.name}
              className={
                field.type === "rich" || field.type === "translatable"
                  ? "sm:col-span-2"
                  : ""
              }
            >
              <ContentField
                tenant={tenant}
                entity={schema.slug}
                field={field}
                value={values[field.name]}
                error={errors[field.name]}
                disabled={save.isPending}
                onChange={(value) =>
                  setValues((current) => ({ ...current, [field.name]: value }))
                }
              />
            </div>
          ))}
        </CardContent>
      </Card>

      <div className="flex items-center gap-2">
        <Button type="submit" disabled={save.isPending}>
          {save.isPending ? t("general.loading") : t("general.save")}
        </Button>
        <Button
          type="button"
          variant="ghost"
          disabled={save.isPending}
          onClick={() =>
            navigate({
              to: "/$tenant/clinic/company-profile/$entity",
              params: { tenant, entity: schema.slug },
            })
          }
        >
          {t("general.cancel")}
        </Button>
      </div>
    </form>
  )
}
