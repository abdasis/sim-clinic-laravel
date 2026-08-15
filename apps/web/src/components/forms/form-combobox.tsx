import { useMemo, useState } from "react"
import type { Control, FieldPath, FieldValues } from "react-hook-form"

import {
  Combobox,
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
}

/**
 * Select yang bisa dicari, terhubung ke react-hook-form. Dipakai saat daftar
 * pilihannya panjang — mencari pasien di antara ratusan nama lewat select
 * biasa tidak praktis.
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
}: FormComboboxProps<T>) {
  const [query, setQuery] = useState("")

  const filtered = useMemo(() => {
    const keyword = query.trim().toLowerCase()

    if (keyword === "") return options

    return options.filter((option) =>
      option.label.toLowerCase().includes(keyword),
    )
  }, [options, query])

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => {
        const selected = options.find((option) => option.value === field.value)

        return (
          <FormItem>
            <FieldLabel label={label} required={required} />
            <FormControl>
              <Combobox
                items={filtered}
                value={field.value ?? ""}
                onValueChange={(value: string | null) => field.onChange(value ?? "")}
                disabled={disabled}
              >
                <ComboboxInput
                  placeholder={placeholder}
                  value={query === "" ? (selected?.label ?? "") : query}
                  onChange={(e) => setQuery(e.target.value)}
                  onBlur={() => setQuery("")}
                  disabled={disabled}
                />
                <ComboboxContent>
                  <ComboboxEmpty>{emptyLabel ?? "—"}</ComboboxEmpty>
                  <ComboboxList>
                    {filtered.map((option) => (
                      <ComboboxItem key={option.value} value={option.value}>
                        {option.label}
                      </ComboboxItem>
                    ))}
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
