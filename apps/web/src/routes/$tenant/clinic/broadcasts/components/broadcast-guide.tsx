import { useTrans } from "#/hooks/use-trans.ts"
import { GuidePanel, type GuideStep } from "./guide-panel.tsx"

interface BroadcastGuideProps {
  templateSelected: boolean
  slotsFilled: boolean
  recipientsReady: boolean
  titleFilled: boolean
  className?: string
}

/** Panel panduan pembuatan broadcast, dirakit dari panel panduan bersama. */
export function BroadcastGuide({
  templateSelected,
  slotsFilled,
  recipientsReady,
  titleFilled,
  className,
}: BroadcastGuideProps) {
  const { t } = useTrans()

  const steps: GuideStep[] = [
    {
      title: t("broadcast.guide_template_title"),
      description: t("broadcast.guide_template_desc"),
      done: templateSelected,
    },
    {
      title: t("broadcast.guide_slots_title"),
      description: t("broadcast.guide_slots_desc"),
      done: slotsFilled,
    },
    {
      title: t("broadcast.guide_recipients_title"),
      description: t("broadcast.guide_recipients_desc"),
      done: recipientsReady,
    },
    {
      title: t("broadcast.guide_send_title"),
      description: t("broadcast.guide_send_desc"),
      done: titleFilled,
    },
  ]

  return (
    <GuidePanel
      title={t("broadcast.guide_title")}
      intro={t("broadcast.guide_intro")}
      steps={steps}
      readyLabel={t("broadcast.guide_ready")}
      notReadyLabel={t("broadcast.guide_not_ready")}
      className={className}
    />
  )
}
