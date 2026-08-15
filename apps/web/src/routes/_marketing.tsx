import { createFileRoute, Outlet, useNavigate } from "@tanstack/react-router"

import { BackToTop } from "#/components/company-profile/back-to-top.tsx"
import { ChatWidget } from "#/components/company-profile/chat-widget.tsx"
import { CompanyLocaleProvider } from "#/components/company-profile/locale-context.tsx"
import { LandingFooter } from "#/components/marketing/landing-footer.tsx"
import { LandingHeader } from "#/components/marketing/landing-header.tsx"
import { LANDING_CONTENT } from "#/lib/landing-content.ts"
import { normalizeLocale, type ContentLocale } from "#/lib/company-locale.ts"

/**
 * Layout tanpa segmen URL: seluruh halaman marketing berbagi header, footer,
 * dan bahasa aktif tanpa mengubah alamatnya.
 */
export const Route = createFileRoute("/_marketing")({
  component: MarketingLayout,
  validateSearch: (search: Record<string, unknown>) => ({
    lang: search.lang ? String(search.lang) : undefined,
  }),
})

function MarketingLayout() {
  const { lang } = Route.useSearch()
  const navigate = useNavigate()
  const locale = normalizeLocale(lang)

  const { settings, navigation } = LANDING_CONTENT

  // Bahasa disimpan di URL supaya tautan yang dibagikan ikut membawanya.
  const switchLocale = (next: ContentLocale) =>
    navigate({ to: ".", search: { lang: next }, replace: true })

  return (
    <CompanyLocaleProvider locale={locale}>
      <div className="flex min-h-svh flex-col">
        <LandingHeader
          settings={settings}
          items={navigation.header}
          locale={locale}
          onLocaleChange={switchLocale}
        />

        <div className="flex-1">
          <Outlet />
        </div>

        <LandingFooter
          settings={settings}
          items={navigation.footer}
          locale={locale}
        />

        <BackToTop label={locale === "en" ? "Back to top" : "Kembali ke atas"} />
        <ChatWidget
          channels={settings?.chat_channels ?? []}
          fallbackLabel={locale === "en" ? "Chat with us" : "Hubungi kami"}
        />
      </div>
    </CompanyLocaleProvider>
  )
}
