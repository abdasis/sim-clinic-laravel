import { createFileRoute, Link } from "@tanstack/react-router"
import { HugeiconsIcon } from "@hugeicons/react"
import { ShoppingCart01Icon } from "@hugeicons/core-free-icons"

import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"

export const Route = createFileRoute("/_marketing/cart/")({
  component: CartPage,
})

function CartPage() {
  // ponytail: keranjang belum menyimpan apa pun. Ceiling: tombol "Tambah" di
  // e-store hanya menautkan ke sini. Upgrade: state keranjang + checkout,
  // dikerjakan bersama backend pesanan.
  return (
    <MarketingPage crumbs={[{ label: "Keranjang" }]} title="Keranjang Anda">
      <div className="flex flex-col items-center gap-4 rounded-lg border border-dashed border-border/50 py-16 text-center">
        <span className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
          <HugeiconsIcon icon={ShoppingCart01Icon} strokeWidth={2} />
        </span>
        <div>
          <p className="text-sm font-medium">Keranjang kosong</p>
          <p className="mt-0.5 text-sm text-muted-foreground">
            Lihat e-store kami dan tambahkan produk yang Anda butuhkan.
          </p>
        </div>
        <Button asChild>
          <Link to="/store">Ke E-Store</Link>
        </Button>
      </div>
    </MarketingPage>
  )
}
