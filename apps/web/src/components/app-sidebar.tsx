import * as React from "react"
import { Link } from "@tanstack/react-router"
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import { SourceCodeIcon } from "@hugeicons/core-free-icons"

import { NavMain } from "#/components/nav-main.tsx"
import { NavSecondary } from "#/components/nav-secondary.tsx"
import { NavUser } from "#/components/nav-user.tsx"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "#/components/ui/sidebar.tsx"

export interface SidebarNavItem {
  title: string
  url: string
  icon: IconSvgElement
  isActive?: boolean
  /**
   * Label grup terjemahan yang sudah di-resolve. Item dengan `group` sama
   * dikelompokkan dalam satu SidebarGroup berlabel; item tanpa `group`
   * dirender di puncak tanpa label.
   */
  group?: string
  items?: { title: string; url: string; isActive?: boolean }[]
}

export interface SidebarUser {
  name: string
  email: string
  avatar?: string
}

interface AppSidebarProps extends React.ComponentProps<typeof Sidebar> {
  brandTitle: string
  brandSubtitle: string
  brandTo?: string
  navMain: SidebarNavItem[]
  navSecondary?: SidebarNavItem[]
  user: SidebarUser
  onLogout?: () => void
  /** Item "Preferensi" di menu pengguna; hanya shell klinik yang mengisinya. */
  preferencesTo?: React.ReactNode
  /** Item "Pengaturan Klinik"; hanya diisi untuk peran yang boleh mengubahnya. */
  clinicSettingsTo?: React.ReactNode
  /** Logo klinik; bila kosong, lencana ikon bawaan yang dipakai. */
  brandLogoUrl?: string | null
}

export function AppSidebar({
  variant = "inset",
  brandTitle,
  brandSubtitle,
  brandTo = "/",
  navMain,
  navSecondary,
  user,
  onLogout,
  preferencesTo,
  clinicSettingsTo,
  brandLogoUrl,
  ...props
}: AppSidebarProps) {
  return (
    <Sidebar variant={variant} {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            {/* Tooltip-nya baru terlihat saat sidebar diciutkan — di situ
                lencana logo tinggal sendirian tanpa nama kliniknya. */}
            <SidebarMenuButton
              size="lg"
              asChild
              tooltip={brandTitle}
              className="active:scale-[0.96]"
            >
              <Link to={brandTo}>
                <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                  {brandLogoUrl ? (
                    <img
                      src={brandLogoUrl}
                      alt={brandTitle}
                      className="size-full object-contain"
                    />
                  ) : (
                    <HugeiconsIcon
                      icon={SourceCodeIcon}
                      strokeWidth={2}
                      className="size-4"
                    />
                  )}
                </div>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-medium">{brandTitle}</span>
                  <span className="truncate text-xs">{brandSubtitle}</span>
                </div>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <NavMain items={navMain} />
        {navSecondary?.length ? (
          <NavSecondary items={navSecondary} className="mt-auto" />
        ) : null}
      </SidebarContent>
      <SidebarFooter>
        <NavUser
          user={user}
          onLogout={onLogout}
          preferencesTo={preferencesTo}
          clinicSettingsTo={clinicSettingsTo}
        />
      </SidebarFooter>
    </Sidebar>
  )
}
