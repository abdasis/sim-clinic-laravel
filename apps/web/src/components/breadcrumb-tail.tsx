import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react"
import { useRouterState } from "@tanstack/react-router"

type Setter = (label: string | undefined) => void

const BreadcrumbTailContext = createContext<Setter | null>(null)
const BreadcrumbTailValueContext = createContext<string | undefined>(undefined)

interface Entry {
  path: string
  label: string
}

/**
 * Ruas terakhir breadcrumb untuk halaman detail.
 *
 * Shell menyusun breadcrumb dari hierarki menu, dan hierarki itu tidak tahu
 * nama pasien atau nomor invoice. Label seperti itu baru ada setelah datanya
 * termuat di komponen, jadi komponennya yang menitipkannya ke atas lewat
 * context — bukan shell yang ikut mengambil datanya sendiri.
 */
export function BreadcrumbTailProvider({
  children,
}: {
  children: React.ReactNode
}) {
  const pathname = useRouterState({ select: (s) => s.location.pathname })
  const [entry, setEntry] = useState<Entry | null>(null)

  // Label disimpan bersama alamat halaman yang menitipkannya, bukan
  // dikosongkan lewat efek saat pindah halaman: efek anak berjalan lebih
  // dulu daripada efek induk, jadi pengosongan di induk justru menghapus
  // label yang baru saja dipasang halaman tujuan.
  const pathRef = useRef(pathname)
  pathRef.current = pathname

  const setTail = useCallback<Setter>((label) => {
    setEntry(label === undefined ? null : { path: pathRef.current, label })
  }, [])

  const tail = entry?.path === pathname ? entry.label : undefined

  return (
    <BreadcrumbTailContext.Provider value={setTail}>
      <BreadcrumbTailValueContext.Provider value={tail}>
        {children}
      </BreadcrumbTailValueContext.Provider>
    </BreadcrumbTailContext.Provider>
  )
}

/** Dibaca shell untuk menyusun ruas terakhir. */
export function useBreadcrumbTailValue(): string | undefined {
  return useContext(BreadcrumbTailValueContext)
}

/**
 * Dipanggil halaman detail dengan label yang sudah dihitungnya.
 *
 * Aman dipanggil di halaman yang berada di luar provider (mis. kasir yang
 * punya kerangka sendiri): tanpa provider, hook ini tidak melakukan apa-apa
 * alih-alih melempar.
 */
export function useBreadcrumbTail(label: string | undefined): void {
  const setTail = useContext(BreadcrumbTailContext)

  useEffect(() => {
    if (!setTail) return

    setTail(label)
  }, [label, setTail])
}
