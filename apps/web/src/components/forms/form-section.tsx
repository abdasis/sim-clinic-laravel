import type { ReactNode } from "react"

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { cn } from "#/lib/utils.ts"

/**
 * Satu kelompok field dalam formulir panjang.
 *
 * Formulir yang panjang dibaca sebagai daftar tanpa ujung kalau seluruh
 * fieldnya ditumpuk rata; dipecah begini, mata punya tempat berhenti dan
 * judulnya menerangkan kenapa field-field itu berkumpul.
 */
export function FormSection({
  title,
  description,
  className,
  children,
}: {
  title: string
  description: string
  /** Untuk section yang isinya satu field lebar, mis. editor rich-text. */
  className?: string
  children: ReactNode
}) {
  return (
    <Card className="border-border/50 shadow-sm">
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className={cn("grid gap-4 sm:grid-cols-2", className)}>
        {children}
      </CardContent>
    </Card>
  )
}
