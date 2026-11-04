import { createFileRoute, Outlet } from "@tanstack/react-router"

import { BackToTop } from "#/components/company-profile/back-to-top.tsx"
import { ChatWidget } from "#/components/company-profile/chat-widget.tsx"
import { LandingFooter } from "#/components/marketing/landing-footer.tsx"
import { LandingHeader } from "#/components/marketing/landing-header.tsx"
import { LANDING_CONTENT } from "#/lib/landing-content.ts"

/**
 * Layout tanpa segmen URL: seluruh halaman marketing berbagi header, footer,
 * dan kamus Indonesia tanpa mengubah alamatnya.
 */
export const Route = createFileRoute("/_marketing")({
  component: MarketingLayout,
})

function MarketingLayout() {
  const { settings, navigation } = LANDING_CONTENT

  return (
    <div className="flex min-h-svh flex-col">
      <LandingHeader settings={settings} items={navigation.header} />

      <div className="flex-1">
        <Outlet />
      </div>

      <LandingFooter settings={settings} items={navigation.footer} />

      <BackToTop label="Kembali ke atas" />
      <ChatWidget
        channels={settings?.chat_channels ?? []}
        fallbackLabel="Hubungi kami"
      />
    </div>
  )
}
