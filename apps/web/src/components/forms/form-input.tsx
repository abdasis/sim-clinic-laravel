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
}

export function FormInput<T extends FieldValues>({
  control,
  name,
  label,
  placeholder,
  type = "text",
  disabled,
  description,
  tooltip,
  required,
  className,
  min,
  max,
  step,
  inputClassName,
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
              placeholder={placeholder}
              disabled={disabled}
              aria-required={required}
              min={min}
              max={max}
              step={step}
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
