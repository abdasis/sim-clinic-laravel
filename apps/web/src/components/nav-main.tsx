import { Link } from "@tanstack/react-router"
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import { ArrowRight01Icon } from "@hugeicons/core-free-icons"

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "#/components/ui/collapsible.tsx"
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuAction,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "#/components/ui/sidebar.tsx"

/**
 * Batang penanda di tepi kiri item aktif. Tumbuh dari tengah saat item
 * menjadi aktif — anchor visual yang tidak menambah warna baru.
 */
const ACTIVE_BAR =
  "before:absolute before:top-1/2 before:left-0 before:h-4 before:w-0.5 before:-translate-y-1/2 before:rounded-full before:bg-sidebar-primary before:transition-transform before:duration-200 before:ease-[var(--ease-in-out)] before:scale-y-0 data-[active=true]:before:scale-y-100"

export function NavMain({
  items,
  groupLabel,
}: {
  items: {
    title: string
    url: string
    icon: IconSvgElement
    isActive?: boolean
    items?: {
      title: string
      url: string
      isActive?: boolean
    }[]
  }[]
  groupLabel?: string
}) {
  return (
    <SidebarGroup>
      {groupLabel ? <SidebarGroupLabel>{groupLabel}</SidebarGroupLabel> : null}
      <SidebarMenu>
        {items.map((item) => (
          <Collapsible key={item.title} asChild defaultOpen={item.isActive}>
            <SidebarMenuItem>
              <SidebarMenuButton
                asChild
                tooltip={item.title}
                isActive={item.isActive}
                className={`relative active:scale-[0.96] ${ACTIVE_BAR}`}
              >
                <Link to={item.url as string}>
                  <HugeiconsIcon icon={item.icon} strokeWidth={2} />
                  <span>{item.title}</span>
                </Link>
              </SidebarMenuButton>
              {item.items?.length ? (
                <>
                  <CollapsibleTrigger asChild>
                    <SidebarMenuAction className="transition-transform duration-200 ease-[var(--ease-in-out)] data-[state=open]:rotate-90">
                      <HugeiconsIcon icon={ArrowRight01Icon} strokeWidth={2} />
                      <span className="sr-only">Buka submenu</span>
                    </SidebarMenuAction>
                  </CollapsibleTrigger>
                  {/*
                    Radix tidak menganimasikan tinggi konten. Trik grid-rows
                    memberi transisi tinggi-otomatis yang bisa dipotong di
                    tengah jalan, tanpa mengukur lewat JS.
                  */}
                  <CollapsibleContent className="grid grid-rows-[0fr] transition-[grid-template-rows] duration-200 ease-[var(--ease-out)] data-[state=open]:grid-rows-[1fr]">
                    <SidebarMenuSub className="min-h-0 overflow-hidden">
                      {item.items?.map((subItem) => (
                        <SidebarMenuSubItem key={subItem.title}>
                          <SidebarMenuSubButton
                            asChild
                            isActive={subItem.isActive}
                            className={`relative data-[active=true]:pl-3.5 data-[active=true]:font-medium ${ACTIVE_BAR}`}
                          >
                            <Link to={subItem.url as string}>
                              <span>{subItem.title}</span>
                            </Link>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                      ))}
                    </SidebarMenuSub>
                  </CollapsibleContent>
                </>
              ) : null}
            </SidebarMenuItem>
          </Collapsible>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  )
}
