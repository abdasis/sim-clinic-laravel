import { useForm as useRHFForm, type UseFormProps } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import type { z } from "zod"

/**
 * Wrapper react-hook-form + zod resolver (T012).
 * Pakai: const form = useForm(schema, { defaultValues })
 */
export function useForm<TSchema extends z.ZodType>(
  schema: TSchema,
  options?: Omit<UseFormProps<z.infer<TSchema>>, "resolver">,
) {
  return useRHFForm<z.infer<TSchema>>({
    resolver: zodResolver(schema as z.ZodType),
    ...options,
  })
}

/**
 * Petakan error validasi Laravel (422) ke field react-hook-form.
 */
export function applyServerErrors(
  form: ReturnType<typeof useRHFForm>,
  errors?: Record<string, string[]>,
) {
  if (!errors) return
  for (const [field, messages] of Object.entries(errors)) {
    form.setError(field as never, { type: "server", message: messages[0] })
  }
}
