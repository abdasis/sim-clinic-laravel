import { useEffect, useState } from "react"
import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Label } from "#/components/ui/label.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useClinicIdentity } from "#/hooks/use-clinic-identity.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { ContentField } from "../company-profile/components/content-field.tsx"

export const Route = createFileRoute("/$tenant/clinic/settings/")({
  component: ClinicSettingsPage,
})

interface ClinicSettings {
  site_name?: Record<string, string> | null
  logo_path?: string | null
  address?: string | null
  tagline?: string | null
}

/**
 * Identitas klinik: yang tampil di sidebar, kop nota, dan halaman publik.
 *
 * Menulis ke endpoint setelan company profile yang sama dengan CMS, tapi
 * hanya mengirim empat field ini — sisanya (tautan sosial, kanal chat) tidak
 * ikut terkirim, jadi tidak mungkin terhapus dari layar yang tidak
 * menampilkannya.
 */
function ClinicSettingsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/settings/" })
  const { t } = useTrans()
  const qc = useQueryClient()

  const [siteName, setSiteName] = useState<Record<string, string>>({})
  const [logoPath, setLogoPath] = useState("")
  const [address, setAddress] = useState("")
  const [tagline, setTagline] = useState("")

  const { data, isLoading } = useQuery({
    queryKey: ["company-settings", tenant],
    queryFn: () =>
      apiGet<{ data: ClinicSettings | null }>(
        `/${tenant}/clinic/company-profile/settings`,
      ),
    select: (res) => res.data,
  })

  // Telepon datang dari identitas, bukan dari setelan profil: nomornya milik
  // pendaftaran klinik dan punya cadangan di tabel tenant.
  const { data: identity } = useClinicIdentity(tenant)

  useEffect(() => {
    if (!data) return

    setSiteName(data.site_name ?? {})
    setLogoPath(data.logo_path ?? "")
    setAddress(data.address ?? "")
    setTagline(data.tagline ?? "")
  }, [data])

  const save = useMutation({
    mutationFn: () =>
      apiPut(`/${tenant}/clinic/company-profile/settings`, {
        site_name: siteName,
        logo_path: logoPath,
        address,
        tagline,
      }),
    onSuccess: () => {
      toast.success(t("company_profile.settings_updated"))
      qc.invalidateQueries({ queryKey: ["company-settings", tenant] })
      // Tanpa ini brand sidebar masih menampilkan nama lama sampai cache
      // lima menitnya habis — persis bagian yang baru saja diubah.
      qc.invalidateQueries({ queryKey: ["clinic-identity", tenant] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  // Cmd/Ctrl+S: satu-satunya pintasan bawaan browser yang layak diambil alih
  // di halaman form — "simpan halaman" tidak berguna di sini.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key !== "s" || !(event.metaKey || event.ctrlKey)) return

      event.preventDefault()

      if (!save.isPending) save.mutate()
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  })

  useBreadcrumbTail(t("clinic.settings"))

  return (
    <div className="max-w-3xl">
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("clinic.settings")}
        </h1>
        <p className="mt-1 text-sm text-pretty text-muted-foreground">
          {t("company_profile.subtitle")}
        </p>
      </div>

      {isLoading ? (
        <Skeleton className="h-80 w-full" />
      ) : (
        <form
          onSubmit={(event) => {
            event.preventDefault()
            save.mutate()
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
                  value={siteName}
                  disabled={save.isPending}
                  onChange={(value) =>
                    setSiteName((value ?? {}) as Record<string, string>)
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
                value={logoPath}
                disabled={save.isPending}
                onChange={(value) => setLogoPath((value as string) ?? "")}
              />

              <div className="space-y-1.5">
                <Label htmlFor="tagline" className="text-sm">
                  {t("company_profile.tagline")}
                </Label>
                <Input
                  id="tagline"
                  value={tagline}
                  disabled={save.isPending}
                  onChange={(event) => setTagline(event.target.value)}
                />
              </div>

              <div className="space-y-1.5 sm:col-span-2">
                <Label htmlFor="address" className="text-sm">
                  {t("company_profile.address")}
                </Label>
                <Textarea
                  id="address"
                  rows={3}
                  value={address}
                  disabled={save.isPending}
                  onChange={(event) => setAddress(event.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="phone" className="text-sm">
                  {t("company_profile.phone")}
                </Label>
                {/* ponytail: phone read-only; tambah UpdateTenantPhoneAction
                    saat bisnis butuh ganti nomor klinik mandiri. */}
                <Input
                  id="phone"
                  readOnly
                  value={identity?.phone ?? ""}
                  className="bg-muted/40 text-muted-foreground"
                />
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  type="submit"
                  disabled={save.isPending}
                  className="transition-transform duration-150 ease-out hover:-translate-y-px"
                >
                  {save.isPending ? t("general.loading") : t("general.save")}
                </Button>
              </TooltipTrigger>
              <TooltipContent className="flex items-center gap-2">
                {t("general.save")}
                <Kbd>Mod+S</Kbd>
              </TooltipContent>
            </Tooltip>
          </div>
        </form>
      )}
    </div>
  )
}
