import { useEffect, useState } from "react"
import { Link } from "@tanstack/react-router"
import { HugeiconsIcon } from "@hugeicons/react"
import { Menu01Icon, ShoppingCart01Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "#/components/ui/sheet.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import type { CompanyNavItem, CompanySettings } from "#/hooks/use-company-profile.ts"
import { contentText } from "#/lib/company-locale.ts"
import { cn } from "#/lib/utils.ts"

interface LandingHeaderProps {
  settings: CompanySettings | null
  items: CompanyNavItem[]
  cartCount?: number
}

/**
 * Header halaman marketing. Menunya datang dari konten statis, jadi
 * bentuknya sama dengan header tenant — yang berbeda hanya tujuannya:
 * halaman di akar situs, bukan di dalam tenant.
 */
export function LandingHeader({
  settings,
  items,
  cartCount = 0,
}: LandingHeaderProps) {
  const text = contentText
  const [scrolled, setScrolled] = useState(false)
  const [open, setOpen] = useState(false)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)

    onScroll()
    window.addEventListener("scroll", onScroll, { passive: true })

    return () => window.removeEventListener("scroll", onScroll)
  }, [])

  const brand = text(settings?.site_name) ?? "Klinik"

  return (
    <header
      className={cn(
        "sticky top-0 z-50 w-full border-b transition-colors duration-200",
        scrolled
          ? "border-border/50 bg-background/85 backdrop-blur-sm"
          : "border-transparent bg-background",
      )}
    >
      <div className="mx-auto flex h-14 w-full max-w-6xl items-center gap-3 px-4">
        <Link
          to="/"
          className="mr-auto flex items-center gap-2 rounded-sm no-underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
          <img
            src={settings?.logo_url || "/logo192.png"}
            alt={brand}
            className="h-7 w-7 object-contain"
          />
          <span className="text-sm font-semibold tracking-tight text-foreground">
            {brand}
          </span>
        </Link>

        <nav className="hidden items-center gap-0.5 lg:flex">
          {items.map((item) => (
            <NavLink key={item.id} item={item} />
          ))}
        </nav>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              asChild
              variant="ghost"
              size="icon"
              className="relative transition-transform duration-200 ease-[var(--ease-out)] active:scale-[0.96]"
            >
              <Link to="/cart" aria-label="Keranjang">
                <HugeiconsIcon icon={ShoppingCart01Icon} strokeWidth={2} />
                {cartCount > 0 ? (
                  <Badge className="absolute -top-0.5 -right-0.5 size-4 justify-center rounded-full p-0 text-xxs tabular-nums">
                    {cartCount}
                  </Badge>
                ) : null}
              </Link>
            </Button>
          </TooltipTrigger>
          <TooltipContent>Keranjang</TooltipContent>
        </Tooltip>

        <Sheet open={open} onOpenChange={setOpen}>
          <SheetTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className="lg:hidden"
              aria-label={brand}
            >
              <HugeiconsIcon icon={Menu01Icon} strokeWidth={2} />
            </Button>
          </SheetTrigger>
          <SheetContent side="right" className="w-72">
            <SheetHeader>
              <SheetTitle>{brand}</SheetTitle>
            </SheetHeader>
            <nav className="flex flex-col gap-1 px-4">
              {items.map((item) => (
                <NavLink
                  key={item.id}
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

const LINK_CLASS =
  "rounded-md px-2.5 py-1.5 text-sm text-muted-foreground no-underline transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"

const CTA_CLASS =
  "rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground no-underline transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"

function NavLink({
  item,
  onNavigate,
}: {
  item: CompanyNavItem
  onNavigate?: () => void
}) {
  const text = contentText

  if (!item.url) return null

  const className = item.is_cta ? CTA_CLASS : LINK_CLASS
  const label = text(item.label)

  if (item.link_type === "anchor_section") {
    return (
      <a
        href={item.url.startsWith("#") ? item.url : `#${item.url}`}
        className={className}
        onClick={onNavigate}
      >
        {label}
      </a>
    )
  }

  if (item.link_type === "external") {
    return (
      <a
        href={item.url}
        target="_blank"
        rel="noreferrer noopener"
        className={className}
        onClick={onNavigate}
      >
        {label}
      </a>
    )
  }

  // Tujuan di akar situs. Path-nya berasal dari konten, jadi belum tentu
  // termasuk route yang dikenal saat kompilasi — pakai anchor biasa.
  return (
    <a href={item.url} className={className} onClick={onNavigate}>
      {label}
    </a>
  )
}
