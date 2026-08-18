import { afterEach, describe, expect, it } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"
import {
  RouterProvider,
  createMemoryHistory,
  createRootRoute,
  createRoute,
  createRouter,
  Outlet,
} from "@tanstack/react-router"

import {
  BreadcrumbTailProvider,
  useBreadcrumbTail,
  useBreadcrumbTailValue,
} from "./breadcrumb-tail.tsx"

function Readout() {
  const tail = useBreadcrumbTailValue()

  return <span data-testid="tail">{tail ?? "-"}</span>
}

function DetailPage({ label }: { label: string }) {
  useBreadcrumbTail(label)

  return <p>{label}</p>
}

function renderAt(path: string, pages: Record<string, React.ReactNode>) {
  const rootRoute = createRootRoute({
    component: () => (
      <BreadcrumbTailProvider>
        <Readout />
        <Outlet />
      </BreadcrumbTailProvider>
    ),
  })

  const routes = Object.entries(pages).map(([p, element]) =>
    createRoute({
      getParentRoute: () => rootRoute,
      path: p,
      component: () => <>{element}</>,
    }),
  )

  const router = createRouter({
    routeTree: rootRoute.addChildren(routes),
    history: createMemoryHistory({ initialEntries: [path] }),
  })

  render(<RouterProvider router={router as never} />)

  return router
}

describe("BreadcrumbTail", () => {
  afterEach(cleanup)

  it("meneruskan label halaman detail ke shell", async () => {
    renderAt("/pasien", { "/pasien": <DetailPage label="Ibu Sinta" /> })

    expect((await screen.findByTestId("tail")).textContent).toBe("Ibu Sinta")
  })

  /**
   * Efek anak berjalan lebih dulu daripada efek induk. Pengosongan tail lewat
   * efek di induk karena itu justru menghapus label yang baru saja dipasang
   * halaman tujuan — label disimpan bersama alamatnya supaya tidak begitu.
   */
  it("melepas label halaman sebelumnya saat pindah halaman", async () => {
    const router = renderAt("/pasien", {
      "/pasien": <DetailPage label="Ibu Sinta" />,
      "/booking": <p>Daftar booking</p>,
    })

    expect((await screen.findByTestId("tail")).textContent).toBe("Ibu Sinta")

    // Router uji memakai pohon rute sendiri; tipe `to` global milik
    // aplikasi tidak mengenalinya.
    await router.navigate({ to: "/booking" } as never)
    await screen.findByText("Daftar booking")

    expect(screen.getByTestId("tail").textContent).toBe("-")
  })

  it("mengganti label saat pindah ke halaman detail lain", async () => {
    const router = renderAt("/pasien", {
      "/pasien": <DetailPage label="Ibu Sinta" />,
      "/pasien-lain": <DetailPage label="Pak Budi" />,
    })

    expect((await screen.findByTestId("tail")).textContent).toBe("Ibu Sinta")

    await router.navigate({ to: "/pasien-lain" } as never)
    await screen.findByText("Pak Budi")

    expect(screen.getByTestId("tail").textContent).toBe("Pak Budi")
  })
})
