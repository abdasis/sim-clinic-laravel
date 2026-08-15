import { Button } from "#/components/ui/button.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"

interface FormSubmitProps {
  children: React.ReactNode
  loading?: boolean
  disabled?: boolean
  className?: string
}

/**
 * Tombol kirim bersama. Saat mengirim, label tetap terbaca dan spinner
 * muncul di sebelahnya — mengganti teks jadi "Memuat..." membuat tombolnya
 * berubah lebar dan kehilangan konteks.
 */
export function FormSubmit({
  children,
  loading,
  disabled,
  className,
}: FormSubmitProps) {
  return (
    <Button
      type="submit"
      disabled={loading || disabled}
      aria-busy={loading}
      className={className}
    >
      {loading ? <Spinner /> : null}
      {children}
    </Button>
  )
}
