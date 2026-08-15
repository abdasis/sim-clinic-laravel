import type { Control, FieldPath, FieldValues } from "react-hook-form"

import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormMessage,
} from "#/components/ui/form.tsx"
import { FieldLabel } from "#/components/forms/field-label.tsx"
import { Switch } from "#/components/ui/switch.tsx"

interface FormSwitchProps<T extends FieldValues> {
  control: Control<T>
  name: FieldPath<T>
  label: string
  /** Keterangan kecil di bawah field. */
  description?: string
  disabled?: boolean
}

/**
 * Saklar boolean terhubung react-hook-form — untuk izin/preferensi seperti
 * opt-in pesan promosi.
 */
export function FormSwitch<T extends FieldValues>({
  control,
  name,
  label,
  description,
  disabled,
}: FormSwitchProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="flex flex-row items-start justify-between gap-3 rounded-md border border-border/50 p-3">
          <div className="min-w-0 space-y-1">
            <FieldLabel label={label} />
            {description ? (
              <FormDescription>{description}</FormDescription>
            ) : null}
            <FormMessage />
          </div>
          <FormControl>
            <Switch
              checked={Boolean(field.value)}
              onCheckedChange={field.onChange}
              disabled={disabled}
              aria-label={label}
            />
          </FormControl>
        </FormItem>
      )}
    />
  )
}
