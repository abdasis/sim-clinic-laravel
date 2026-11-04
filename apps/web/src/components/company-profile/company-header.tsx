import { useEffect, useState } from "react"
import { Link } from "@tanstack/react-router"
import { Menu } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "#/components/ui/sheet.tsx"
import { contentText } from "#/lib/company-locale.ts"
import { cn } from "#/lib/utils.ts"
import type {
  CompanyNavItem,
  CompanySettings,
} from "#/hooks/use-company-profile.ts"
import { internalHref } from "./cta-link.tsx"

interface CompanyHeaderProps {
  tenant: string
  settings: CompanySettings | null
  items: CompanyNavItem[]
}

/**
 * Header landing: menu dari database.
 */
export function CompanyHeader({
  tenant,
  settings,
  items,
}: CompanyHeaderProps) {
  const text = contentText
  const [scrolled, setScrolled] = useState(false)
  const [open, setOpen] = useState(false)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)

    onScroll()
    window.addEventListener("scroll", onScroll, { passive: true })

    return () => window.removeEventListener("scroll", onScroll)
  }, [])

  const brand = text(settings?.site_name) ?? tenant

  return (
    <header
      className={cn(
        "sticky top-0 z-50 w-full border-b transition-colors duration-200",
        scrolled
          ? "border-border/50 bg-background/85 backdrop-blur-sm"
          : "border-transparent bg-background",
      )}
    >
      <div className="mx-auto flex h-14 w-full max-w-6xl items-center gap-4 px-4">
        <Link
          to="/$tenant"
          params={{ tenant }}
          className="flex items-center gap-2 rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
          <img
            src={settings?.logo_url || "/logo192.png"}
            alt={brand}
            className="h-7 w-auto object-contain"
          />
          <span className="text-sm font-semibold tracking-tight">{brand}</span>
        </Link>

        <nav className="ml-auto hidden items-center gap-1 md:flex">
          {items.map((item) => (
            <NavLink key={item.id} tenant={tenant} item={item} />
          ))}
        </nav>

        <Sheet open={open} onOpenChange={setOpen}>
          <SheetTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className="ml-auto md:hidden"
              aria-label={brand}
            >
              <Menu className="size-4" />
            </Button>
          </SheetTrigger>
          <SheetContent side="right" className="w-64">
            <SheetHeader>
              <SheetTitle>{brand}</SheetTitle>
            </SheetHeader>
            <nav className="flex flex-col gap-1 px-4">
              {items.map((item) => (
                <NavLink
                  key={item.id}
                  tenant={tenant}
                  item={item}
                  onNavigate={() => setOpen(false)}
                />
              ))}
            </nav>
          </SheetContent>
        </Sheet>
      </div>
    </header>
  )
}

function NavLink({
  tenant,
  item,
  onNavigate,
}: {
  tenant: string
  item: CompanyNavItem
  onNavigate?: () => void
}) {
  const text = contentText

  if (!item.url) return null

  const href =
    item.link_type === "route_internal"
      ? internalHref(tenant, item.url)
      : item.link_type === "anchor_section"
        ? item.url.startsWith("#")
          ? item.url
          : `#${item.url}`
        : item.url

  // Satu-dua menu memang dimaksudkan tampil sebagai tombol, mis. booking.
  const className = item.is_cta
    ? "rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
    : "rounded-md px-2.5 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"

  return (
    <a
      href={href}
      className={className}
      onClick={onNavigate}
      {...(item.link_type === "external"
        ? { target: "_blank", rel: "noreferrer noopener" }
        : {})}
    >
      {text(item.label)}
    </a>
  )
}
