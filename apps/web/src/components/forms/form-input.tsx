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
  required?: boolean
  /** Lebar field di dalam grid section, mis. `sm:col-span-2`. */
  className?: string
}

export function FormInput<T extends FieldValues>({
  control,
  name,
  label,
  placeholder,
  type = "text",
  disabled,
  description,
  required,
  className,
}: FormInputProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FieldLabel label={label} required={required} />
          <FormControl>
            <Input
              type={type}
              placeholder={placeholder}
              disabled={disabled}
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
