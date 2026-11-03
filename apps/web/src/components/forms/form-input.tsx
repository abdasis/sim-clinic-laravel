import type { Control, FieldPath, FieldValues } from "react-hook-form"
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormMessage,
} from "#/components/ui/form.tsx"
import { FieldLabel } from "#/components/forms/field-label.tsx"
import { Input } from "#/components/ui/input.tsx"

interface FormInputProps<T extends FieldValues> {
  control: Control<T>
  name: FieldPath<T>
  label: string
  placeholder?: string
  type?: string
  disabled?: boolean
  /** Keterangan kecil di bawah field. */
  description?: string
  /** Penjelasan singkat di samping label untuk field yang tidak sebening namanya. */
  tooltip?: string
  required?: boolean
  /** Lebar field di dalam grid section, mis. `sm:col-span-2`. */
  className?: string
  /** Batas dan langkah untuk `type="number"`. */
  min?: number
  max?: number
  step?: number
  /** Kelas tambahan untuk elemen input, mis. `tabular-nums`. */
  inputClassName?: string
  /**
   * Petunjuk pengisian otomatis untuk peramban dan pengelola kata sandi.
   *
   * Penting pada kolom kata sandi: tanpa `new-password`, peramban mengisi
   * kolom kata sandi baru dengan yang lama, dan penggunanya menekan simpan
   * tanpa sadar tidak mengganti apa pun.
   */
  autoComplete?: string
  /**
   * Papan tombol apa yang dibuka ponsel, dan apa tulisan tombol kirimnya.
   *
   * Keduanya diturunkan dari `type` bila tidak diisi — itu yang benar untuk
   * hampir semua field. Satu-satunya yang perlu diisi tangan: nomor telepon
   * yang disimpan sebagai teks (supaya nol di depan tidak hilang) tapi tetap
   * layak dapat papan angka, dan field terakhir sebuah formulir yang pantas
   * bertuliskan "selesai" alih-alih "berikutnya".
   */
  inputMode?: React.ComponentProps<"input">["inputMode"]
  enterKeyHint?: React.ComponentProps<"input">["enterKeyHint"]
}

/**
 * Papan tombol bawaan menurut jenis field.
 *
 * `type="number"` sudah memunculkan papan angka sendiri, jadi yang perlu
 * dibantu justru yang tidak: email dan telepon yang disimpan sebagai teks.
 * Di papan tombol ponsel, beda antara deretan huruf dan deretan angka itu
 * beda antara dua ketukan dan enam.
 */
const PAPAN_TOMBOL: Record<string, React.ComponentProps<"input">["inputMode"]> = {
  email: "email",
  tel: "tel",
  number: "decimal",
  url: "url",
  search: "search",
}

export function FormInput<T extends FieldValues>({
  control,
  name,
  label,
  placeholder,
  type = "text",
  autoComplete,
  disabled,
  description,
  tooltip,
  required,
  className,
  min,
  max,
  step,
  inputClassName,
  inputMode,
  enterKeyHint,
}: FormInputProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FieldLabel label={label} required={required} tooltip={tooltip} />
          <FormControl>
            <Input
              type={type}
              autoComplete={autoComplete}
              placeholder={placeholder}
              disabled={disabled}
              aria-required={required}
              min={min}
              max={max}
              step={step}
              inputMode={inputMode ?? PAPAN_TOMBOL[type]}
              enterKeyHint={enterKeyHint}
              className={inputClassName}
              {...field}
              value={field.value ?? ""}
            />
          </FormControl>
          {description ? (
            <FormDescription>{description}</FormDescription>
          ) : null}
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
