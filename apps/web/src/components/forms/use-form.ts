import {
  useForm as useRHFForm,
  type FieldValues,
  type Path,
  type UseFormProps,
  type UseFormReturn,
} from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import type { z } from "zod"

/**
 * Wrapper react-hook-form + zod resolver (T012).
 * Pakai: const form = useForm(schema, { defaultValues })
 */
export function useForm<TSchema extends z.ZodType<FieldValues, any, any>>(
  schema: TSchema,
  options?: Omit<UseFormProps<z.output<TSchema>>, "resolver">,
): UseFormReturn<z.output<TSchema>> {
  return useRHFForm<z.output<TSchema>>({
    // zod v4 + @hookform/resolvers v5 typings are overly strict across the
    // generic boundary; the resolver is correct at runtime.
    resolver: zodResolver(schema as never) as never,
    ...options,
  })
}

/**
 * Petakan error validasi Laravel (422) ke field react-hook-form.
 */
export function applyServerErrors<T extends FieldValues>(
  form: UseFormReturn<T>,
  errors?: Record<string, string[]>,
) {
  if (!errors) return
  for (const [field, messages] of Object.entries(errors)) {
    form.setError(field as Path<T>, { type: "server", message: messages[0] })
  }
}
