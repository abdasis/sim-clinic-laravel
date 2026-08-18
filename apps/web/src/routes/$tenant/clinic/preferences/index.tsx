import { createFileRoute, useParams } from "@tanstack/react-router"
import { useEffect, useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import {
  ComputerIcon,
  LayoutLeftIcon,
  Moon02Icon,
  SidebarLeftIcon,
  Sun03Icon,
} from "@hugeicons/core-free-icons"

import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import {
  applyAppearanceToDom,
  getStoredAppearance,
  persistAppearance,
  useMe,
} from "#/hooks/use-appearance.ts"
import { apiPatch } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { getAuthUser, setAuthUser } from "#/lib/auth.ts"
import type { AuthUser } from "#/lib/auth.ts"
import { cn } from "#/lib/utils.ts"
import {
  ACCENTS,
  DEFAULT_APPEARANCE,
  MODES,
  SIDEBAR_VARIANTS,
} from "#/types/appearance.ts"
import {
  normalizeAppearance,
} from "#/types/appearance.ts"
import type { Appearance, SidebarVariant, ThemeMode } from "#/types/appearance.ts"
import { OptionTile, SectionCard } from "./components/preference-controls.tsx"

export const Route = createFileRoute("/$tenant/clinic/preferences/")({
  component: PreferencesPage,
})

interface MeResponse {
  data: AuthUser
}

const MODE_ICON: Record<ThemeMode, typeof Sun03Icon> = {
  light: Sun03Icon,
  dark: Moon02Icon,
  auto: ComputerIcon,
}

const VARIANT_ICON: Record<SidebarVariant, typeof SidebarLeftIcon> = {
  sidebar: SidebarLeftIcon,
  floating: LayoutLeftIcon,
  inset: LayoutLeftIcon,
}

function PreferencesPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/preferences/" })
  const { t } = useTrans()
  const qc = useQueryClient()

  const me = useMe(tenant)
  // ponytail: state lokal supaya pilihan terasa seketika; server jadi sumber
  // kebenaran begitu /me menjawab, dan localStorage disamakan di sini juga.
  const [draft, setDraft] = useState<Appearance>(DEFAULT_APPEARANCE)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    setDraft(getStoredAppearance())
    setReady(true)
  }, [me.data])

  const mutation = useMutation({
    mutationFn: (next: Appearance) => apiPatch<MeResponse>(`/${tenant}/me`, next),
    onMutate: async (next) => {
      // Batalkan refetch /me yang sedang berjalan supaya tidak menimpa cache
      // saat kita menulis nilai optimistik.
      await qc.cancelQueries({ queryKey: ["me", tenant] })
      const previous = qc.getQueryData<MeResponse>(["me", tenant])
      qc.setQueryData<MeResponse>(["me", tenant], (old) => {
        const user = old?.data ?? getAuthUser()
        if (!user) return old
        return { data: { ...user, appearance: next } }
      })
      return { previous }
    },
    onSuccess: (data, next) => {
      // Server sudah mengonfirmasi; samakan localStorage + cache dengan hasil
      // server supaya tidak perlu refetch yang berisiko menimpa.
      const appearance = normalizeAppearance(data.data.appearance ?? next)
      setAuthUser({ ...data.data, appearance })
      applyAppearanceToDom(appearance)
      qc.setQueryData<MeResponse>(["me", tenant], { data: data.data })
      toast.success(t("preferences.saved"))
    },
    onError: (err: ApiError, _next, ctx) => {
      // Tampilannya sudah berubah duluan; kembalikan ke nilai tersimpan
      // supaya layar tidak berbohong tentang apa yang benar-benar disimpan.
      if (ctx?.previous) {
        qc.setQueryData(["me", tenant], ctx.previous)
      }
      const restored = getStoredAppearance()
      setDraft(restored)
      applyAppearanceToDom(restored)
      toast.error(err.message || t("preferences.save_failed"))
    },
    onSettled: () => {
      // Refetch background aman: useMe tidak lagi menimpa localStorage saat
      // server mengembalikan appearance null.
      qc.invalidateQueries({ queryKey: ["me", tenant] })
    },
  })

  /** Pratinjau langsung diterapkan, lalu disimpan — bukan sebaliknya. */
  const choose = (patch: Partial<Appearance>) => {
    const next = { ...draft, ...patch }
    setDraft(next)
    applyAppearanceToDom(next)
    persistAppearance(next)
    mutation.mutate(next)
  }

  return (
    <div className="mx-auto w-full max-w-3xl">

      <div className="mb-6">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("preferences.title")}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {t("preferences.description")}
        </p>
      </div>

      {!ready ? (
        <div className="space-y-4">
          <Skeleton className="h-56 w-full rounded-lg" />
          <Skeleton className="h-40 w-full rounded-lg" />
        </div>
      ) : (
        <div className="space-y-4">
          <SectionCard
            title={t("preferences.accent")}
            description={t("preferences.accent_desc")}
          >
            <div className="grid grid-cols-4 gap-2 sm:grid-cols-7">
              {ACCENTS.map((accent) => (
                <Tooltip key={accent.key}>
                  <TooltipTrigger asChild>
                    <button
                      type="button"
                      aria-pressed={draft.accent === accent.key}
                      aria-label={t(`preferences.accents.${accent.key}`)}
                      onClick={() => choose({ accent: accent.key })}
                      className={cn(
                        "flex flex-col items-center gap-1.5 rounded-md border p-2",
                        "transition-[border-color,background-color,transform] duration-150 ease-out",
                        "hover:-translate-y-px hover:bg-accent/40",
                        "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                        draft.accent === accent.key
                          ? "border-foreground/40 bg-muted"
                          : "border-border/50",
                      )}
                    >
                      <span
                        className="size-6 rounded-full ring-1 ring-black/10 dark:ring-white/15"
                        style={{ backgroundColor: accent.swatch }}
                      />
                      <span className="truncate text-xxs text-muted-foreground">
                        {t(`preferences.accents.${accent.key}`)}
                      </span>
                    </button>
                  </TooltipTrigger>
                  <TooltipContent>
                    {t(`preferences.accents.${accent.key}`)}
                  </TooltipContent>
                </Tooltip>
              ))}
            </div>
          </SectionCard>

          <SectionCard
            title={t("preferences.mode")}
            description={t("preferences.mode_desc")}
          >
            <div className="grid grid-cols-3 gap-2">
              {MODES.map((mode) => (
                <OptionTile
                  key={mode}
                  icon={MODE_ICON[mode]}
                  label={t(`preferences.${mode}`)}
                  selected={draft.mode === mode}
                  onSelect={() => choose({ mode })}
                />
              ))}
            </div>
          </SectionCard>

          <SectionCard
            title={t("preferences.sidebar_variant")}
            description={t("preferences.sidebar_variant_desc")}
          >
            <div className="grid grid-cols-3 gap-2">
              {SIDEBAR_VARIANTS.map((variant) => (
                <OptionTile
                  key={variant}
                  icon={VARIANT_ICON[variant]}
                  label={t(`preferences.variant_${variant}`)}
                  hint={t(`preferences.variant_${variant}_desc`)}
                  selected={draft.sidebar_variant === variant}
                  onSelect={() => choose({ sidebar_variant: variant })}
                />
              ))}
            </div>
          </SectionCard>
        </div>
      )}
    </div>
  )
}
