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

type NavMainItem = {
  title: string
  url: string
  icon: IconSvgElement
  isActive?: boolean
  group?: string
  items?: {
    title: string
    url: string
    isActive?: boolean
  }[]
}

/**
 * Membagi `items` menjadi kelompok berdasarkan field `group`. Urutan grup
 * mengikuti kemunculan pertama di array (stabil, bukan alfabetis). Item
 * tanpa `group` dikumpulkan di satu grup tanpa label di puncak.
 */
function partitionByGroup(items: NavMainItem[]): { label?: string; items: NavMainItem[] }[] {
  const groups: { label?: string; items: NavMainItem[] }[] = []
  const indexByKey = new Map<string, number>()

  for (const item of items) {
    const key = item.group ?? ""
    let idx = indexByKey.get(key)

    if (idx === undefined) {
      idx = groups.length
      indexByKey.set(key, idx)
      groups.push({ label: item.group, items: [] })
    }

    groups[idx].items.push(item)
  }

  return groups
}

export function NavMain({ items }: { items: NavMainItem[] }) {
  const groups = partitionByGroup(items)

  return (
    <>
      {groups.map((group) => (
        <SidebarGroup key={group.label ?? "__ungrouped"}>
          {group.label ? <SidebarGroupLabel>{group.label}</SidebarGroupLabel> : null}
          <SidebarMenu>
            {group.items.map((item) => (
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
      ))}
    </>
  )
}
