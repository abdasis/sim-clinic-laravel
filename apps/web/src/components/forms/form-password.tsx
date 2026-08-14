import { useState } from "react"
import { Eye, EyeOff } from "lucide-react"
import type { Control, FieldPath, FieldValues } from "react-hook-form"

import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "#/components/ui/form.tsx"
import { Input } from "#/components/ui/input.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"

interface FormPasswordProps<T extends FieldValues> {
  control: Control<T>
  name: FieldPath<T>
  label: string
  placeholder?: string
  /** Petunjuk syarat kata sandi, ditampilkan di bawah field. */
  description?: string
  showLabel: string
  hideLabel: string
}

/**
 * Field kata sandi dengan tombol lihat/sembunyikan — dipakai form staf,
 * registrasi, dan penerimaan undangan agar perilakunya seragam.
 */
export function FormPassword<T extends FieldValues>({
  control,
  name,
  label,
  placeholder,
  description,
  showLabel,
  hideLabel,
}: FormPasswordProps<T>) {
  const [visible, setVisible] = useState(false)
  const Icon = visible ? EyeOff : Eye
  const toggleLabel = visible ? hideLabel : showLabel

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <div className="relative">
              <Input
                type={visible ? "text" : "password"}
                placeholder={placeholder}
                className="pr-9"
                {...field}
                value={field.value ?? ""}
              />
              <TooltipProvider>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <button
                      type="button"
                      onClick={() => setVisible((v) => !v)}
                      aria-label={toggleLabel}
                      aria-pressed={visible}
                      className="absolute inset-y-0 right-0 flex w-9 items-center justify-center rounded-r-md text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                    >
                      <Icon className="size-4" />
                    </button>
                  </TooltipTrigger>
                  <TooltipContent>{toggleLabel}</TooltipContent>
                </Tooltip>
              </TooltipProvider>
            </div>
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
