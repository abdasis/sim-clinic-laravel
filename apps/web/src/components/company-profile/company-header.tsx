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
import { cn } from "#/lib/utils.ts"
import { CONTENT_LOCALES, type ContentLocale } from "#/lib/company-locale.ts"
import type { CompanyNavItem, CompanySettings } from "#/hooks/use-company-profile.ts"
import { internalHref } from "./cta-link.tsx"

interface CompanyHeaderProps {
  tenant: string
  settings: CompanySettings | null
  items: CompanyNavItem[]
  locale: ContentLocale
  languageLabel: string
  onLocaleChange: (locale: ContentLocale) => void
}

/**
 * Header landing: menu dari database, dan pengalih bahasa yang mengubah
 * konten sekaligus label sistem.
 */
export function CompanyHeader({
  tenant,
  settings,
  items,
  locale,
  languageLabel,
  onLocaleChange,
}: CompanyHeaderProps) {
  const [scrolled, setScrolled] = useState(false)
  const [open, setOpen] = useState(false)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)

    onScroll()
    window.addEventListener("scroll", onScroll, { passive: true })

    return () => window.removeEventListener("scroll", onScroll)
  }, [])

  const brand = settings?.brand_name ?? tenant

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
          search={{ lang: locale }}
          className="flex items-center gap-2 rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
          {settings?.logo_url ? (
            <img src={settings.logo_url} alt={brand} className="h-7 w-auto" />
          ) : null}
          <span className="text-sm font-semibold tracking-tight">{brand}</span>
        </Link>

        <nav className="ml-auto hidden items-center gap-1 md:flex">
          {items.map((item) => (
            <NavLink key={item.id} tenant={tenant} item={item} />
          ))}
        </nav>

        <LocaleSwitcher
          locale={locale}
          label={languageLabel}
          onChange={onLocaleChange}
          className="ml-auto md:ml-0"
        />

        <Sheet open={open} onOpenChange={setOpen}>
          <SheetTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className="md:hidden"
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
  const className =
    "rounded-md px-2.5 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"

  const href =
    item.link_type === "route_internal"
      ? internalHref(tenant, item.link_value)
      : item.link_type === "anchor_section"
        ? item.link_value.startsWith("#")
          ? item.link_value
          : `#${item.link_value}`
        : item.link_value

  return (
    <a
      href={href}
      className={className}
      onClick={onNavigate}
      {...(item.link_type === "external"
        ? { target: "_blank", rel: "noreferrer noopener" }
        : {})}
    >
      {item.label}
    </a>
  )
}

function LocaleSwitcher({
  locale,
  label,
  onChange,
  className,
}: {
  locale: ContentLocale
  label: string
  onChange: (locale: ContentLocale) => void
  className?: string
}) {
  return (
    <div
      role="group"
      aria-label={label}
      className={cn(
        "flex items-center gap-0.5 rounded-md border border-border/50 p-0.5",
        className,
      )}
    >
      {CONTENT_LOCALES.map((code) => (
        <button
          key={code}
          type="button"
          aria-pressed={code === locale}
          onClick={() => onChange(code)}
          className={cn(
            "rounded-sm px-2 py-0.5 text-xs font-medium tracking-wide uppercase transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
            code === locale
              ? "bg-muted text-foreground"
              : "text-muted-foreground hover:text-foreground",
          )}
        >
          {code}
        </button>
      ))}
    </div>
  )
}
