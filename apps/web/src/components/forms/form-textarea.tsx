import type { Control, FieldPath, FieldValues } from "react-hook-form"
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormMessage,
} from "#/components/ui/form.tsx"
import { FieldLabel } from "#/components/forms/field-label.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"

interface FormTextareaProps<T extends FieldValues> {
  control: Control<T>
  name: FieldPath<T>
  label: string
  placeholder?: string
  rows?: number
  /** Keterangan kecil di bawah field. */
  description?: string
  required?: boolean
  /** Lebar field di dalam grid section, mis. `sm:col-span-2`. */
  className?: string
}

export function FormTextarea<T extends FieldValues>({
  control,
  name,
  label,
  placeholder,
  rows = 3,
  description,
  required,
  className,
}: FormTextareaProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FieldLabel label={label} required={required} />
          <FormControl>
            <Textarea
              rows={rows}
              placeholder={placeholder}
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
