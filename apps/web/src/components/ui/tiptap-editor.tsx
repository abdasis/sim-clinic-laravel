import { useEffect } from "react"
import { EditorContent, useEditor } from "@tiptap/react"
import {
  Bold,
  Heading2,
  ImageIcon,
  Italic,
  Link2,
  List,
  ListOrdered,
  Strikethrough,
} from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import { Separator } from "#/components/ui/separator.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { cn } from "#/lib/utils.ts"
import { RICH_TEXT_EXTENSIONS, type RichTextDoc } from "#/lib/tiptap-render.tsx"

interface TiptapEditorProps {
  value?: RichTextDoc | null
  onChange: (value: RichTextDoc | null) => void
  placeholder?: string
  disabled?: boolean
  className?: string
  /**
   * Sembunyikan tombol gambar. Untuk field yang isinya dibaca mesin, bukan
   * dirender — gambar tidak punya arti di sana dan hanya jadi tautan mati.
   */
  disableImage?: boolean
}

/**
 * Editor rich-text untuk field narasi CMS. Nilainya dokumen Tiptap JSON,
 * bukan HTML — sama persis dengan yang dirender halaman publik.
 */
export function TiptapEditor({
  value,
  onChange,
  placeholder,
  disabled,
  className,
  disableImage = false,
}: TiptapEditorProps) {
  const editor = useEditor({
    extensions: RICH_TEXT_EXTENSIONS,
    content: value ?? null,
    editable: !disabled,
    editorProps: {
      attributes: {
        class:
          "prose prose-sm dark:prose-invert max-w-none min-h-32 px-3 py-2 focus:outline-none",
        ...(placeholder ? { "data-placeholder": placeholder } : {}),
      },
    },
    onUpdate: ({ editor: instance }) => {
      const doc = instance.getJSON() as RichTextDoc

      onChange(instance.isEmpty ? null : doc)
    },
  })

  // Berganti bahasa menukar isi editor dari luar; tanpa ini tab EN masih
  // menampilkan teks ID yang sedang diketik.
  useEffect(() => {
    if (!editor) return

    const current = editor.getJSON()

    if (JSON.stringify(current) === JSON.stringify(value ?? null)) return

    editor.commands.setContent(value ?? "", { emitUpdate: false })
  }, [editor, value])

  if (!editor) return null

  return (
    <div
      className={cn(
        "overflow-hidden rounded-md border border-border/50 bg-background transition-colors focus-within:ring-2 focus-within:ring-ring",
        disabled && "opacity-60",
        className,
      )}
    >
      <div className="flex flex-wrap items-center gap-0.5 border-b border-border/50 bg-muted/30 px-1.5 py-1">
        <ToolbarButton
          label="Tebal"
          shortcut="Mod+B"
          active={editor.isActive("bold")}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleBold().run()}
        >
          <Bold className="size-3.5" />
        </ToolbarButton>
        <ToolbarButton
          label="Miring"
          shortcut="Mod+I"
          active={editor.isActive("italic")}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleItalic().run()}
        >
          <Italic className="size-3.5" />
        </ToolbarButton>
        <ToolbarButton
          label="Coret"
          shortcut="Mod+Shift+S"
          active={editor.isActive("strike")}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleStrike().run()}
        >
          <Strikethrough className="size-3.5" />
        </ToolbarButton>

        <Separator orientation="vertical" className="mx-1 h-4" />

        <ToolbarButton
          label="Judul"
          active={editor.isActive("heading", { level: 2 })}
          disabled={disabled}
          onClick={() =>
            editor.chain().focus().toggleHeading({ level: 2 }).run()
          }
        >
          <Heading2 className="size-3.5" />
        </ToolbarButton>
        <ToolbarButton
          label="Daftar poin"
          active={editor.isActive("bulletList")}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleBulletList().run()}
        >
          <List className="size-3.5" />
        </ToolbarButton>
        <ToolbarButton
          label="Daftar bernomor"
          active={editor.isActive("orderedList")}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
        >
          <ListOrdered className="size-3.5" />
        </ToolbarButton>

        <Separator orientation="vertical" className="mx-1 h-4" />

        <ToolbarButton
          label="Tautan"
          active={editor.isActive("link")}
          disabled={disabled}
          onClick={() => {
            const previous = editor.getAttributes("link").href as
              | string
              | undefined
            const href = window.prompt("Alamat tautan", previous ?? "https://")

            if (href === null) return

            if (href === "") {
              editor.chain().focus().unsetLink().run()

              return
            }

            editor.chain().focus().setLink({ href }).run()
          }}
        >
          <Link2 className="size-3.5" />
        </ToolbarButton>
        {/* Ekstensi gambarnya sengaja tidak dilepas: dokumen lama yang sudah
            memuat gambar tetap harus bisa dibuka dan disimpan tanpa isinya
            hilang. Yang dimatikan hanya cara menambah yang baru. */}
        {disableImage ? null : (
          <ToolbarButton
            label="Gambar"
            disabled={disabled}
            onClick={() => {
              const src = window.prompt("Alamat gambar", "https://")

              if (!src) return

              editor.chain().focus().setImage({ src }).run()
            }}
          >
            <ImageIcon className="size-3.5" />
          </ToolbarButton>
        )}
      </div>

      <EditorContent editor={editor} />
    </div>
  )
}

function ToolbarButton({
  label,
  shortcut,
  active,
  disabled,
  onClick,
  children,
}: {
  label: string
  shortcut?: string
  active?: boolean
  disabled?: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          aria-label={label}
          aria-pressed={active}
          disabled={disabled}
          onClick={onClick}
          className={cn("size-7", active && "bg-muted text-foreground")}
        >
          {children}
        </Button>
      </TooltipTrigger>
      <TooltipContent className="flex items-center gap-2">
        {label}
        {shortcut ? (
          <kbd className="rounded-sm bg-background/20 px-1 font-mono text-[10px]">
            {shortcut}
          </kbd>
        ) : null}
      </TooltipContent>
    </Tooltip>
  )
}
