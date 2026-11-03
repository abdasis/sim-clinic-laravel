import { useTrans } from "#/hooks/use-trans.ts"
import { GuidePanel, type GuideStep } from "./guide-panel.tsx"

interface TemplateGuideProps {
  nameValid: boolean
  headerComplete: boolean
  bodyFilled: boolean
  className?: string
}

/**
 * Panduan pembuatan template. Langkah peninjauan Meta tidak pernah berstatus
 * selesai di halaman ini: keputusan ada di luar aplikasi.
 */
export function TemplateGuide({
  nameValid,
  headerComplete,
  bodyFilled,
  className,
}: TemplateGuideProps) {
  const { t } = useTrans()

  const steps: GuideStep[] = [
    {
      title: t("broadcast.template_guide_name_title"),
      description: t("broadcast.template_guide_name_desc"),
      done: nameValid,
    },
    {
      title: t("broadcast.template_guide_header_title"),
      description: t("broadcast.template_guide_header_desc"),
      done: headerComplete,
    },
    {
      title: t("broadcast.template_guide_body_title"),
      description: t("broadcast.template_guide_body_desc"),
      done: bodyFilled,
    },
    {
      title: t("broadcast.template_guide_review_title"),
      description: t("broadcast.template_guide_review_desc"),
      done: false,
    },
  ]

  return (
    <GuidePanel
      title={t("broadcast.template_guide_title")}
      intro={t("broadcast.template_guide_intro")}
      steps={steps}
      readyLabel={t("broadcast.template_guide_ready")}
      notReadyLabel={t("broadcast.template_guide_not_ready")}
      note={t("broadcast.template_status_pending_note")}
      className={className}
    />
  )
}
