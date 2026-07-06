import { CheckIcon, PlusCircleIcon } from "lucide-react"
import type { Column } from "@tanstack/react-table"
import { Button } from "#/components/ui/button.tsx"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "#/components/ui/popover.tsx"
import { Checkbox } from "#/components/ui/checkbox.tsx"
import type { FacetedOption } from "#/types/data-table.ts"
import { cn } from "#/lib/utils.ts"

interface DataTableFacetedFilterProps<TData, TValue> {
  column?: Column<TData, TValue>
  title?: string
  options: FacetedOption[]
}

export function DataTableFacetedFilter<TData, TValue>({
  column,
  title,
  options,
}: DataTableFacetedFilterProps<TData, TValue>) {
  if (!column) return null

  const selected = (column.getFilterValue() as string[]) ?? []
  const toggle = (value: string) => {
    const next = selected.includes(value)
      ? selected.filter((v) => v !== value)
      : [...selected, value]
    column.setFilterValue(next.length ? next.join(",") : undefined)
  }

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" size="sm" className="h-8 border-dashed">
          <PlusCircleIcon className="size-3.5" />
          {title}
          {selected.length > 0 && (
            <>
              <span className="bg-primary text-primary-foreground rounded-sm px-1 py-0.5 text-xs">
                {selected.length}
              </span>
            </>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-48 p-0" align="start">
        <div className="p-1">
          {options.map((option) => {
            const checked = selected.includes(option.value)
            return (
              <button
                type="button"
                key={option.value}
                onClick={() => toggle(option.value)}
                className={cn(
                  "flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent",
                )}
              >
                <div
                  className={cn(
                    "flex size-4 items-center justify-center border rounded-sm",
                    checked
                      ? "bg-primary text-primary-foreground"
                      : "opacity-50",
                  )}
                >
                  {checked && <CheckIcon className="size-3.5" />}
                </div>
                <Checkbox checked={checked} className="sr-only" tabIndex={-1} />
                <span>{option.label}</span>
              </button>
            )
          })}
        </div>
      </PopoverContent>
    </Popover>
  )
}