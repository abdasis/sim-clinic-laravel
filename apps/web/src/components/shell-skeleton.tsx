import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "#/components/ui/sidebar.tsx"
import { Separator } from "#/components/ui/separator.tsx"

export function ShellSkeleton({ navCount = 8 }: { navCount?: number }) {
  return (
    <SidebarProvider>
      <Sidebar variant="inset">
        <SidebarHeader>
          <Skeleton className="h-12 w-full rounded-lg" />
        </SidebarHeader>
        <SidebarContent>
          <div className="flex flex-col gap-2 p-2">
            <Skeleton className="h-8 w-28 rounded-md" />
            <div className="flex flex-col gap-1">
              {Array.from({ length: navCount }).map((_, i) => (
                <Skeleton key={i} className="h-8 w-full rounded-md" />
              ))}
            </div>
          </div>
        </SidebarContent>
        <SidebarFooter>
          <Skeleton className="h-12 w-full rounded-md" />
        </SidebarFooter>
      </Sidebar>
      <SidebarInset>
        <header className="flex h-12 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger />
          <Separator orientation="vertical" className="mr-1 h-4" />
          <Skeleton className="h-4 w-32" />
        </header>
        <main className="flex-1 p-4">
          <div className="flex flex-col gap-3">
            <Skeleton className="h-8 w-48 rounded-md" />
            <Skeleton className="h-8 w-full rounded-md" />
            <Skeleton className="h-8 w-full rounded-md" />
            <Skeleton className="h-8 w-3/4 rounded-md" />
          </div>
        </main>
      </SidebarInset>
    </SidebarProvider>
  )
}