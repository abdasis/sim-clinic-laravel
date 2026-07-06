import { Button } from "#/components/ui/button.tsx"

interface FormSubmitProps {
  children: React.ReactNode
  loading?: boolean
  disabled?: boolean
}

export function FormSubmit({ children, loading, disabled }: FormSubmitProps) {
  return (
    <Button type="submit" disabled={loading || disabled}>
      {children}
    </Button>
  )
}
