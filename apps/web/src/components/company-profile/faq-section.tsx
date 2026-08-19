import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "#/components/ui/accordion.tsx"
import type { CompanyFaq } from "#/hooks/use-company-profile.ts"
import { useContentText } from "./locale-context.tsx"
import { SectionShell, type SectionTone } from "./section-shell.tsx"

interface FaqSectionProps {
  id?: string
  eyebrow?: string
  heading?: string
  description?: string
  tone?: SectionTone
  items: CompanyFaq[]
}

/**
 * Tanya-jawab sebagai akordeon.
 *
 * Semuanya tertutup saat halaman dibuka: daftar pertanyaan yang bisa dipindai
 * sekilas lebih berguna daripada dinding jawaban yang harus dilewati untuk
 * menemukan satu pertanyaan sendiri. Yang dibuka pun tidak menutup yang lain,
 * karena orang kerap membandingkan dua jawaban sekaligus.
 */
export function FaqSection({
  id,
  eyebrow,
  heading,
  description,
  tone,
  items,
}: FaqSectionProps) {
  const text = useContentText()

  if (items.length === 0) return null

  return (
    <SectionShell
      id={id}
      eyebrow={eyebrow}
      title={heading}
      description={description}
      tone={tone}
    >
      <Accordion
        type="multiple"
        className="mx-auto w-full max-w-3xl divide-y divide-border/50 border-y border-border/50"
      >
        {items.map((faq) => (
          <AccordionItem
            key={faq.id}
            value={String(faq.id)}
            className="border-none"
          >
            <AccordionTrigger className="py-4 text-left text-sm font-medium hover:no-underline sm:text-base">
              {text(faq.question)}
            </AccordionTrigger>
            <AccordionContent className="pb-4 text-sm leading-relaxed text-pretty text-muted-foreground">
              {text(faq.answer)}
            </AccordionContent>
          </AccordionItem>
        ))}
      </Accordion>
    </SectionShell>
  )
}
