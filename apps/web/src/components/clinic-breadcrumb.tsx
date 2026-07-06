import { Link } from "@tanstack/react-router"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "#/components/ui/breadcrumb.tsx"

export interface Crumb {
  label: string
  to?: string
  params?: Record<string, string>
}

/**
 * Breadcrumb hierarki untuk halaman klinik (CLAUDE.md wajib).
 * Item terakhir = BreadcrumbPage (bukan link), item lain = link nyata.
 */
export function ClinicBreadcrumb({ items }: { items: Crumb[] }) {
  return (
    <Breadcrumb className="mb-4">
      <BreadcrumbList>
        {items.map((item, index) => {
          const isLast = index === items.length - 1
          return (
            <BreadcrumbItem key={index}>
              {isLast || !item.to ? (
                <BreadcrumbPage>{item.label}</BreadcrumbPage>
              ) : (
                <>
                  <BreadcrumbLink asChild>
                    <Link to={item.to} params={item.params}>
                      {item.label}
                    </Link>
                  </BreadcrumbLink>
                  <BreadcrumbSeparator />
                </>
              )}
            </BreadcrumbItem>
          )
        })}
      </BreadcrumbList>
    </Breadcrumb>
  )
}
