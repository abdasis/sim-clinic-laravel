import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "#/components/ui/select.tsx"
import { Switch } from "#/components/ui/switch.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"
import { TiptapEditor } from "#/components/ui/tiptap-editor.tsx"
import { useTranslatableField } from "#/hooks/use-translatable-field.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import type { Translatable } from "#/lib/company-locale.ts"
import type { RichTextDoc } from "#/lib/tiptap-render.tsx"
import type { FieldSchema } from "./entity-schema.ts"
import { LocaleTabs } from "./locale-tabs.tsx"
import { MediaField } from "./media-field.tsx"

interface ContentFieldProps {
  tenant: string
  entity: string
  field: FieldSchema
  value: unknown
  error?: string
  onChange: (value: unknown) => void
  disabled?: boolean
}

/**
 * Satu baris form CMS. Bentuknya ditentukan skema entitas, jadi menambah
 * field cukup mengubah data — bukan menulis komponen baru.
 */
export function ContentField({
  tenant,
  entity,
  field,
  value,
  error,
  onChange,
  disabled,
}: ContentFieldProps) {
  const { t } = useTrans()
  const label = t(field.labelKey)
  const id = `field-${field.name}`

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between gap-2">
        <Label htmlFor={id} className="text-sm">
          {label}
          {field.required ? (
            <span className="ml-0.5 text-destructive">*</span>
          ) : null}
        </Label>
      </div>

      <FieldControl
        id={id}
        tenant={tenant}
        entity={entity}
        field={field}
        value={value}
        onChange={onChange}
        disabled={disabled}
      />

      {error ? <p className="text-xs text-destructive">{error}</p> : null}
    </div>
  )
}

function FieldControl({
  id,
  tenant,
  entity,
  field,
  value,
  onChange,
  disabled,
}: {
  id: string
  tenant: string
  entity: string
  field: FieldSchema
  value: unknown
  onChange: (value: unknown) => void
  disabled?: boolean
}) {
  const { t } = useTrans()
  const translatable = useTranslatableField()

  switch (field.type) {
    case "translatable": {
      const map = (value ?? {}) as Translatable<string>

      return (
        <div className="space-y-1.5">
          <LocaleTabs
            locale={translatable.locale}
            filled={translatable.filledLocales(map)}
            onChange={translatable.setLocale}
          />
          <Textarea
            id={id}
            rows={2}
            disabled={disabled}
            value={translatable.read(map) ?? ""}
            onChange={(event) =>
              onChange(translatable.write(map, event.target.value))
            }
          />
        </div>
      )
    }

    case "rich": {
      const map = (value ?? {}) as Translatable<RichTextDoc>

      return (
        <div className="space-y-1.5">
          <LocaleTabs
            locale={translatable.locale}
            filled={translatable.filledLocales(map)}
            onChange={translatable.setLocale}
          />
          <TiptapEditor
            disabled={disabled}
            value={translatable.read(map) ?? null}
            onChange={(doc) => onChange(translatable.write(map, doc))}
          />
        </div>
      )
    }

    case "image":
      return (
        <MediaField
          tenant={tenant}
          entity={entity}
          value={value as string}
          onChange={onChange}
          disabled={disabled}
        />
      )

    case "select":
      return (
        <Select
          value={(value as string) || undefined}
          onValueChange={onChange}
          disabled={disabled}
        >
          <SelectTrigger id={id}>
            <SelectValue placeholder="—" />
          </SelectTrigger>
          <SelectContent>
            {field.options?.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {t(option.labelKey)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )

    case "boolean":
      return (
        <Switch
          id={id}
          checked={Boolean(value)}
          onCheckedChange={onChange}
          disabled={disabled}
        />
      )

    case "number":
      return (
        <Input
          id={id}
          type="number"
          className="tabular-nums"
          disabled={disabled}
          value={(value as number | string) ?? ""}
          onChange={(event) =>
            onChange(event.target.value === "" ? null : Number(event.target.value))
          }
        />
      )

    case "tags": {
      const tags = (value ?? []) as string[]

      return (
        <Input
          id={id}
          disabled={disabled}
          value={tags.join(", ")}
          placeholder="laser, rejuvenation"
          onChange={(event) =>
            onChange(
              event.target.value
                .split(",")
                .map((tag) => tag.trim())
                .filter(Boolean),
            )
          }
        />
      )
    }

    default:
      return (
        <Input
          id={id}
          type={field.type === "url" ? "url" : "text"}
          disabled={disabled}
          value={(value as string) ?? ""}
          onChange={(event) => onChange(event.target.value)}
        />
      )
  }
}
