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

interface FormDatePickerProps<T extends FieldValues> {
  control: Control<T>
  name: FieldPath<T>
  label: string
  withTime?: boolean
  /** Batas atas tanggal, mis. hari ini untuk tanggal lahir. */
  max?: string
  /** Keterangan kecil di bawah field. */
  description?: string
  required?: boolean
  /** Lebar field di dalam grid section, mis. `sm:col-span-2`. */
  className?: string
}

/**
 * Date/datetime picker sederhana berbasis input native (T012).
 * withTime=true → datetime-local untuk booking start/end.
 */
export function FormDatePicker<T extends FieldValues>({
  control,
  name,
  label,
  withTime = false,
  max,
  description,
  required,
  className,
}: FormDatePickerProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FieldLabel label={label} required={required} />
          <FormControl>
            <Input
              type={withTime ? "datetime-local" : "date"}
              max={max}
              aria-required={required}
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
