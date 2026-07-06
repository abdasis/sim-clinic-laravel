import * as React from "react"
import { Command, type LucideIcon } from "lucide-react"
import { Link } from "@tanstack/react-router"

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
  icon: LucideIcon
  isActive?: boolean
  items?: { title: string; url: string }[]
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
  groupLabel?: string
  navMain: SidebarNavItem[]
  navSecondary?: SidebarNavItem[]
  user: SidebarUser
  onLogout?: () => void
}

export function AppSidebar({
  brandTitle,
  brandSubtitle,
  brandTo = "/",
  groupLabel,
  navMain,
  navSecondary,
  user,
  onLogout,
  ...props
}: AppSidebarProps) {
  return (
    <Sidebar variant="inset" {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link to={brandTo}>
                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                  <Command className="size-4" />
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
        <NavMain items={navMain} groupLabel={groupLabel} />
        {navSecondary?.length ? (
          <NavSecondary items={navSecondary} className="mt-auto" />
        ) : null}
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={user} onLogout={onLogout} />
      </SidebarFooter>
    </Sidebar>
  )
}
