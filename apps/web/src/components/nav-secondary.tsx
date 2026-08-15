import * as React from "react"
import { Link } from "@tanstack/react-router"
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"

import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "#/components/ui/sidebar.tsx"

export function NavSecondary({
  items,
  ...props
}: {
  items: {
    title: string
    url: string
    icon: IconSvgElement
  }[]
} & React.ComponentPropsWithoutRef<typeof SidebarGroup>) {
  return (
    <SidebarGroup {...props}>
      <SidebarGroupContent>
        <SidebarMenu>
          {items.map((item) => (
            <SidebarMenuItem key={item.title}>
              <SidebarMenuButton asChild size="sm" className="active:scale-[0.96]">
                <Link to={item.url as string}>
                  <HugeiconsIcon icon={item.icon} strokeWidth={2} />
                  <span>{item.title}</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          ))}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  )
}
