import {
  Empty,
  EmptyAction,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "#/components/ui/empty.tsx"
import {
  EmptyIllustration,
  type EmptyIllustrationName,
} from "#/components/ui/empty-illustration.tsx"

interface EmptyStateProps {
  title: string
  description?: string
  illustration?: EmptyIllustrationName
  /** Aksi opsional, mis. tombol yang membuka dialog tambah. */
  action?: React.ReactNode
  className?: string
}

/**
 * Susunan baku keadaan kosong: ilustrasi, judul, keterangan, aksi opsional.
 *
 * Primitif `Empty` tetap terbuka untuk kasus yang butuh susunan lain, tapi dua
 * belas pemakai merangkai bagian yang sama persis — jadi susunannya tinggal di
 * satu tempat dan halaman cukup menyebut isinya.
 */
export function EmptyState({
  title,
  description,
  illustration,
  action,
  className,
}: EmptyStateProps) {
  return (
    <Empty className={className}>
      <EmptyMedia>
        <EmptyIllustration name={illustration} />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>{title}</EmptyTitle>
        {description ? <EmptyDescription>{description}</EmptyDescription> : null}
      </EmptyHeader>
      {action ? <EmptyAction>{action}</EmptyAction> : null}
    </Empty>
  )
}
