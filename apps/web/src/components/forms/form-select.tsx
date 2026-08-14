import type { Control, FieldPath, FieldValues } from "react-hook-form"
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "#/components/ui/form.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"

export interface SelectOption {
  label: string
  value: string
}

interface FormSelectProps<T extends FieldValues> {
  control: Control<T>
  name: FieldPath<T>
  label: string
  options: SelectOption[]
  placeholder?: string
  disabled?: boolean
  /** Keterangan kecil di bawah field, mis. alasan field terkunci. */
  description?: string
}

export function FormSelect<T extends FieldValues>({
  control,
  name,
  label,
  options,
  placeholder,
  disabled,
  description,
}: FormSelectProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <NativeSelect {...field} value={field.value ?? ""} disabled={disabled}>
              {placeholder ? <NativeSelectOption value="">{placeholder}</NativeSelectOption> : null}
              {options.map((opt) => (
                <NativeSelectOption key={opt.value} value={opt.value}>
                  {opt.label}
                </NativeSelectOption>
              ))}
            </NativeSelect>
          </FormControl>
          {description ? <FormDescription>{description}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
