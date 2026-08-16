import { useMemo } from "react"
import type { Control, FieldPath, FieldValues } from "react-hook-form"

import {
  Combobox,
  ComboboxCollection,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "#/components/ui/combobox.tsx"
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormMessage,
} from "#/components/ui/form.tsx"
import { FieldLabel } from "#/components/forms/field-label.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { SelectOption } from "#/components/forms/form-select.tsx"

interface FormComboboxProps<T extends FieldValues> {
  control: Control<T>
  name: FieldPath<T>
  label: string
  options: SelectOption[]
  placeholder?: string
  emptyLabel?: string
  disabled?: boolean
  description?: string
  required?: boolean
  /**
   * Wadah portal untuk daftar pilihan. Wajib diisi bila combobox dipakai di
   * dalam drawer atau dialog.
   */
  container?: HTMLElement | null
  /** Daftar pilihannya masih diambil dari server. */
  loading?: boolean
  /**
   * Pengambilan daftar pilihan gagal. Tanpa ini, daftar yang gagal dimuat
   * tampak sama dengan daftar yang memang kosong — dan pengguna menyimpulkan
   * fieldnya rusak.
   */
  error?: boolean
}

/**
 * Select yang bisa dicari, terhubung ke react-hook-form. Dipakai saat daftar
 * pilihannya panjang — mencari pasien di antara ratusan nama lewat select
 * biasa tidak praktis.
 *
 * Penyaringan dan teks input sepenuhnya milik Base UI: `items` diberi objek
 * `{label, value}` yang bentuknya sudah dikenali pustaka, sehingga label
 * tampil sendiri saat terpilih. Sebelumnya wrapper ini mengendalikan `value`
 * dan `onChange` milik input, yang menimpa penangan bawaan Base UI sehingga
 * ketikan tidak pernah sampai ke pustaka dan daftarnya tidak pernah terbuka.
 */
export function FormCombobox<T extends FieldValues>({
  control,
  name,
  label,
  options,
  placeholder,
  emptyLabel,
  disabled,
  description,
  required,
  container,
  loading,
  error,
}: FormComboboxProps<T>) {
  const { t } = useTrans()

  const emptyText = useMemo(() => {
    if (loading) return t("general.loading")
    if (error) return t("general.load_failed")

    return emptyLabel ?? t("general.no_data")
  }, [loading, error, emptyLabel, t])

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => {
        const selected =
          options.find((option) => option.value === field.value) ?? null

        return (
          <FormItem>
            <FieldLabel label={label} required={required} />
            <FormControl>
              <Combobox
                items={options}
                value={selected}
                onValueChange={(option: SelectOption | null) =>
                  field.onChange(option?.value ?? "")
                }
                disabled={disabled || loading}
              >
                <ComboboxInput placeholder={placeholder} disabled={disabled} />
                <ComboboxContent container={container}>
                  <ComboboxEmpty>{emptyText}</ComboboxEmpty>
                  <ComboboxList>
                    <ComboboxCollection>
                      {(option: SelectOption) => (
                        <ComboboxItem key={option.value} value={option}>
                          {option.label}
                        </ComboboxItem>
                      )}
                    </ComboboxCollection>
                  </ComboboxList>
                </ComboboxContent>
              </Combobox>
            </FormControl>
            {description ? (
              <FormDescription>{description}</FormDescription>
            ) : null}
            <FormMessage />
          </FormItem>
        )
      }}
    />
  )
}
