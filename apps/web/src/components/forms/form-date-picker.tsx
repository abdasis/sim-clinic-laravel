import type { Control, FieldPath, FieldValues } from "react-hook-form"
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "#/components/ui/form.tsx"
import { Input } from "#/components/ui/input.tsx"

interface FormDatePickerProps<T extends FieldValues> {
  control: Control<T>
  name: FieldPath<T>
  label: string
  withTime?: boolean
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
}: FormDatePickerProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Input
              type={withTime ? "datetime-local" : "date"}
              {...field}
              value={field.value ?? ""}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
