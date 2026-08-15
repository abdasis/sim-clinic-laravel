import { createFileRoute, Link, useParams } from "@tanstack/react-router"

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useDashboardSummary } from "#/hooks/use-dashboard.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { BookingStatusChart } from "./components/booking-status-chart.tsx"
import { DashboardHero } from "./components/dashboard-hero.tsx"
import { DashboardKpiRow } from "./components/dashboard-kpis.tsx"
import { RevenueTrendChart } from "./components/revenue-trend-chart.tsx"
import { TodaySchedule } from "./components/today-schedule.tsx"
import { TopServicesChart } from "./components/top-services-chart.tsx"

const TREND_DAYS = 14

export const Route = createFileRoute("/$tenant/clinic/")({
  component: DashboardPage,
})

function DashboardPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/" })
  const { t } = useTrans()
  const user = useAuthUser()

  const { data, isLoading, isError } = useDashboardSummary(tenant, TREND_DAYS)
  const summary = data?.data

  return (
    <div className="space-y-4">
      <DashboardHero
        tenant={tenant}
        userName={user?.name}
        clinicRole={user?.clinic_role ?? ""}
      />

      {isError ? (
        <Card className="border-destructive/40">
          <CardContent className="py-6 text-center text-sm text-destructive">
            {t("dashboard.load_failed")}
          </CardContent>
        </Card>
      ) : null}

      <DashboardKpiRow
        kpis={summary?.kpis}
        trend={summary?.revenue_trend}
        isLoading={isLoading}
      />

      <div className="grid gap-4 lg:grid-cols-3">
        <Panel
          className="lg:col-span-2"
          title={t("dashboard.revenue_trend")}
          subtitle={t("dashboard.revenue_trend_subtitle").replace(
            ":days",
            String(TREND_DAYS),
          )}
          delay={90}
        >
          {isLoading || !summary ? (
            <Skeleton className="h-56 w-full" />
          ) : (
            <RevenueTrendChart points={summary.revenue_trend} />
          )}
        </Panel>

        <Panel
          title={t("dashboard.booking_status")}
          subtitle={t("dashboard.booking_status_subtitle")}
          delay={135}
        >
          {isLoading || !summary ? (
            <Skeleton className="h-56 w-full" />
          ) : (
            <BookingStatusChart items={summary.booking_status_counts} />
          )}
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel
          title={t("dashboard.top_services")}
          subtitle={t("dashboard.top_services_subtitle")}
          delay={180}
        >
          {isLoading || !summary ? (
            <Skeleton className="h-56 w-full" />
          ) : (
            <TopServicesChart items={summary.top_services} />
          )}
        </Panel>

        <Panel
          title={t("dashboard.schedule_today")}
          subtitle={t("dashboard.schedule_today_subtitle")}
          delay={225}
          action={
            <Button asChild variant="ghost" size="sm">
              <Link to="/$tenant/clinic/bookings" params={{ tenant }}>
                {t("dashboard.view_all")}
              </Link>
            </Button>
          }
        >
          {isLoading || !summary ? (
            <Skeleton className="h-56 w-full" />
          ) : (
            <TodaySchedule tenant={tenant} entries={summary.schedule_today} />
          )}
        </Panel>
      </div>
    </div>
  )
}

function Panel({
  title,
  subtitle,
  delay,
  action,
  className,
  children,
}: {
  title: string
  subtitle: string
  delay: number
  action?: React.ReactNode
  className?: string
  children: React.ReactNode
}) {
  return (
    <Card
      className={`rise-in ${className ?? ""}`}
      style={{ animationDelay: `${delay}ms` }}
    >
      <CardHeader className="flex-row items-start justify-between gap-3">
        <div>
          <CardTitle className="text-base">{title}</CardTitle>
          <p className="mt-0.5 text-xs text-muted-foreground">{subtitle}</p>
        </div>
        {action}
      </CardHeader>
      <CardContent>{children}</CardContent>
    </Card>
  )
}
