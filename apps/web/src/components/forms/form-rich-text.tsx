import type { FieldPath, FieldValues, UseFormReturn } from "react-hook-form"

import { Label } from "#/components/ui/label.tsx"
import { TiptapEditor } from "#/components/ui/tiptap-editor.tsx"
import type { RichTextDoc } from "#/lib/tiptap-render.tsx"

interface FormRichTextProps<T extends FieldValues> {
  form: UseFormReturn<T>
  name: FieldPath<T>
  /** Dilewatkan bila judul section di atasnya sudah menamai field ini. */
  label?: string
  /** Petunjuk yang tampil di editor selagi kosong. */
  placeholder: string
  disabled?: boolean
}

/**
 * Editor rich-text yang nilainya dititipkan ke react-hook-form.
 *
 * Tidak lewat `register` seperti field lain: Tiptap memegang state-nya
 * sendiri dan tidak pernah melahirkan event `change` pada elemen form. Yang
 * dilakukan di sini hanya menaruh dokumennya ke form supaya ikut terkirim
 * bersama field lain, tanpa jalur simpan sendiri.
 */
export function FormRichText<T extends FieldValues>({
  form,
  name,
  label,
  placeholder,
  disabled,
}: FormRichTextProps<T>) {
  return (
    <div className="space-y-1.5">
      {label ? <Label>{label}</Label> : null}
      <TiptapEditor
        disableImage
        value={form.watch(name) as RichTextDoc | null}
        disabled={disabled}
        placeholder={placeholder}
        onChange={(doc) =>
          form.setValue(name, doc as never, { shouldDirty: true })
        }
      />
    </div>
  )
}
