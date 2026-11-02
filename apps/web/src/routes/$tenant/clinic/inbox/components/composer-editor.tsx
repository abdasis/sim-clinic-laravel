import { useEffect, useRef } from "react"
import { EditorContent, useEditor, type Editor } from "@tiptap/react"
import StarterKit from "@tiptap/starter-kit"

import { cn } from "#/lib/utils.ts"
import { parseWhatsapp, serializeWhatsapp } from "./whatsapp-format.ts"

interface ComposerEditorProps {
  value: string
  label: string
  placeholder: string
  canSend: boolean
  disabled?: boolean
  onChange: (value: string) => void
  onSend: () => void
}

const EXTENSIONS = [
  StarterKit.configure({
    heading: false,
    blockquote: false,
    codeBlock: false,
    horizontalRule: false,
    underline: false,
    link: false,
    dropcursor: false,
    gapcursor: false,
    trailingNode: false,
  }),
]

/** Shortcut format memakai `Alt` supaya tidak bentrok dengan `Mod+B` sidebar. */
const FORMAT_BY_CODE: Record<string, (editor: Editor) => void> = {
  KeyB: (editor) => editor.chain().focus().toggleBold().run(),
  KeyI: (editor) => editor.chain().focus().toggleItalic().run(),
  KeyS: (editor) => editor.chain().focus().toggleStrike().run(),
  KeyM: (editor) => editor.chain().focus().toggleCode().run(),
  Digit7: (editor) => editor.chain().focus().toggleOrderedList().run(),
  Digit8: (editor) => editor.chain().focus().toggleBulletList().run(),
}

/* Shortcut bawaan editor (`Mod+B/I/U/S`) dimatikan: `Mod+B` milik sidebar. */
const BLOCKED_MOD_KEYS = new Set(["b", "i", "u", "s"])

const CONTENT_CLASS = [
  "min-h-10 max-h-40 overflow-y-auto px-3 py-2 text-sm leading-relaxed outline-none scrollbar-thin",
  "[&_p]:my-0 [&_p+p]:mt-1",
  "[&_ul]:my-1 [&_ul]:list-disc [&_ul]:ps-5",
  "[&_ol]:my-1 [&_ol]:list-decimal [&_ol]:ps-5",
  "[&_li>p]:my-0",
  "[&_code]:rounded-sm [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.8em]",
].join(" ")

/**
 * Editor balasan bergaya WhatsApp. Isinya dokumen Tiptap, tetapi yang keluar
 * lewat `onChange` selalu teks WhatsApp — backend meneruskannya apa adanya.
 */
export function ComposerEditor({
  value,
  label,
  placeholder,
  canSend,
  disabled,
  onChange,
  onSend,
}: ComposerEditorProps) {
  const editorRef = useRef<Editor | null>(null)
  const onChangeRef = useRef(onChange)
  const onSendRef = useRef(onSend)
  const canSendRef = useRef(canSend)

  onChangeRef.current = onChange
  onSendRef.current = onSend
  canSendRef.current = canSend

  const editor = useEditor({
    extensions: EXTENSIONS,
    content: parseWhatsapp(value),
    editable: !disabled,
    editorProps: {
      attributes: { class: CONTENT_CLASS },
      handleKeyDown: (_view, event) => {
        const modified = event.metaKey || event.ctrlKey

        if (modified && BLOCKED_MOD_KEYS.has(event.key.toLowerCase())) {
          event.preventDefault()
          event.stopPropagation()

          return true
        }

        if (event.altKey && !modified && !event.shiftKey) {
          const command = FORMAT_BY_CODE[event.code]
          const current = editorRef.current

          if (command && current?.isEditable) {
            event.preventDefault()
            command(current)

            return true
          }
        }

        // Ctrl/Cmd+Enter mengirim. Enter biasa tetap milik editor sebagai
        // baris baru — balasan klinik sering butuh paragraf.
        if (event.key === "Enter" && modified && !event.isComposing) {
          event.preventDefault()
          if (canSendRef.current) onSendRef.current()

          return true
        }

        return false
      },
    },
    onCreate: ({ editor: instance }) => {
      editorRef.current = instance
    },
    onUpdate: ({ editor: instance }) => {
      onChangeRef.current(serializeWhatsapp(instance.getJSON()))
    },
  })

  useEffect(() => {
    editor?.setEditable(!disabled)
  }, [disabled, editor])

  // Perubahan dari luar (kirim sukses, draft dikembalikan) menimpa isi editor.
  // Bandingkan teksnya lebih dulu supaya ketikan sendiri tidak memicu setContent
  // yang akan melompatkan kursor.
  useEffect(() => {
    if (!editor) return
    if (serializeWhatsapp(editor.getJSON()) === value) return

    editor.commands.setContent(value ? parseWhatsapp(value) : "", {
      emitUpdate: false,
    })
  }, [editor, value])

  // Atribut aksesibilitas dipasang di sini, bukan saat membuat editor, agar
  // label ikut berubah begitu terjemahan selesai dimuat.
  useEffect(() => {
    const element = editor?.view.dom
    if (!element) return

    element.setAttribute("role", "textbox")
    element.setAttribute("aria-multiline", "true")
    element.setAttribute("aria-label", label)
    element.setAttribute("data-testid", "reply-textarea")
  }, [editor, label])

  if (!editor) return null

  return (
    <div className={cn("relative", disabled && "opacity-60")}>
      <EditorContent editor={editor} />
      {value === "" ? (
        <span
          aria-hidden
          className="pointer-events-none absolute inset-x-3 top-2 truncate text-sm text-muted-foreground"
        >
          {placeholder}
        </span>
      ) : null}
    </div>
  )
}
