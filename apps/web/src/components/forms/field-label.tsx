import { FormLabel } from "#/components/ui/form.tsx"

/**
 * Label field dengan penanda wajib. Tandanya dekoratif — `aria-required` di
 * input yang memberi tahu pembaca layar, jadi bintangnya disembunyikan supaya
 * tidak dibacakan sebagai "bintang" di tengah nama field.
 */
export function FieldLabel({
  label,
  required,
}: {
  label: string
  required?: boolean
}) {
  return (
    <FormLabel>
      {label}
      {required ? (
        <span aria-hidden="true" className="text-destructive">
          *
        </span>
      ) : null}
    </FormLabel>
  )
}
