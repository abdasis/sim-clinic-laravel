import { useEffect, useState } from "react"
import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"
import { Label } from "#/components/ui/label.tsx"
import { Input } from "#/components/ui/input.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { ContentField } from "./components/content-field.tsx"

export const Route = createFileRoute("/$tenant/clinic/company-profile/settings")(
  { component: CompanyProfileSettingsPage },
)

interface Settings {
  site_name?: Record<string, string> | null
  logo_path?: string | null
  copyright_text?: string | null
  chat_channels: unknown[]
  social_links: unknown[]
  marketplace_links: unknown[]
  default_locale: string
}

/** Daftar tautan/kanal disunting sebagai JSON — bentuknya bebas per klinik. */
const JSON_FIELDS = [
  { name: "chat_channels", labelKey: "company_profile.chat_widget" },
  { name: "social_links", labelKey: "company_profile.social_links" },
  { name: "marketplace_links", labelKey: "company_profile.url" },
] as const

function CompanyProfileSettingsPage() {
  const { tenant } = useParams({
    from: "/$tenant/clinic/company-profile/settings",
  })
  const { t } = useTrans()
  const qc = useQueryClient()
  const [values, setValues] = useState<Record<string, unknown>>({})
  const [rawJson, setRawJson] = useState<Record<string, string>>({})
  const [jsonErrors, setJsonErrors] = useState<Record<string, boolean>>({})

  const { data, isLoading } = useQuery({
    queryKey: ["company-settings", tenant],
    queryFn: () =>
      apiGet<{ data: Settings | null }>(
        `/${tenant}/clinic/company-profile/settings`,
      ),
    select: (res) => res.data,
  })

  useEffect(() => {
    if (!data) return

    setValues({
      site_name: data.site_name ?? {},
      logo_path: data.logo_path ?? "",
      copyright_text: data.copyright_text ?? "",
      default_locale: data.default_locale ?? "id",
    })
    setRawJson({
      chat_channels: JSON.stringify(data.chat_channels ?? [], null, 2),
      social_links: JSON.stringify(data.social_links ?? [], null, 2),
      marketplace_links: JSON.stringify(data.marketplace_links ?? [], null, 2),
    })
  }, [data])

  const save = useMutation({
    mutationFn: () => {
      const payload: Record<string, unknown> = { ...values }

      for (const field of JSON_FIELDS) {
        payload[field.name] = JSON.parse(rawJson[field.name] || "[]")
      }

      return apiPut(`/${tenant}/clinic/company-profile/settings`, payload)
    },
    onSuccess: () => {
      toast.success(t("company_profile.settings_updated"))
      qc.invalidateQueries({ queryKey: ["company-settings", tenant] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const parseJson = (name: string, text: string) => {
    setRawJson((current) => ({ ...current, [name]: text }))

    try {
      JSON.parse(text || "[]")
      setJsonErrors((current) => ({ ...current, [name]: false }))
    } catch {
      setJsonErrors((current) => ({ ...current, [name]: true }))
    }
  }

  const hasJsonError = Object.values(jsonErrors).some(Boolean)

  useBreadcrumbTail(t("company_profile.settings"))
  return (
    <div>
      <h1 className="mb-4 text-xl font-semibold tracking-tight">
        {t("company_profile.settings")}
      </h1>

      {isLoading ? (
        <Skeleton className="h-96 w-full" />
      ) : (
        <form
          onSubmit={(event) => {
            event.preventDefault()

            if (!hasJsonError) save.mutate()
          }}
          className="space-y-4"
        >
          <Card>
            <CardHeader>
              <CardTitle className="text-base">
                {t("company_profile.brand_name")}
              </CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <ContentField
                  tenant={tenant}
                  entity="settings"
                  field={{
                    name: "site_name",
                    labelKey: "company_profile.brand_name",
                    type: "translatable",
                  }}
                  value={values.site_name}
                  disabled={save.isPending}
                  onChange={(value) =>
                    setValues((current) => ({ ...current, site_name: value }))
                  }
                />
              </div>
              <ContentField
                tenant={tenant}
                entity="settings"
                field={{
                  name: "logo_path",
                  labelKey: "company_profile.logo",
                  type: "image",
                }}
                value={values.logo_path}
                disabled={save.isPending}
                onChange={(value) =>
                  setValues((current) => ({ ...current, logo_path: value }))
                }
              />
              <div className="space-y-1.5">
                <Label htmlFor="copyright_text" className="text-sm">
                  {t("company_profile.meta_title")}
                </Label>
                <Input
                  id="copyright_text"
                  disabled={save.isPending}
                  value={(values.copyright_text as string) ?? ""}
                  onChange={(event) =>
                    setValues((current) => ({
                      ...current,
                      copyright_text: event.target.value,
                    }))
                  }
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">
                {t("company_profile.social_links")}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {JSON_FIELDS.map((field) => (
                <div key={field.name} className="space-y-1.5">
                  <Label htmlFor={field.name} className="text-sm">
                    {t(field.labelKey)}
                  </Label>
                  <Textarea
                    id={field.name}
                    rows={5}
                    spellCheck={false}
                    disabled={save.isPending}
                    value={rawJson[field.name] ?? "[]"}
                    onChange={(event) => parseJson(field.name, event.target.value)}
                    className="font-mono text-xs"
                  />
                  {jsonErrors[field.name] ? (
                    <p className="text-xs text-destructive">JSON</p>
                  ) : null}
                </div>
              ))}
            </CardContent>
          </Card>

          <Button type="submit" disabled={save.isPending || hasJsonError}>
            {save.isPending ? t("general.loading") : t("general.save")}
          </Button>
        </form>
      )}
    </div>
  )
}
