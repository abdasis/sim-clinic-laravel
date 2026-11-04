import { useState } from "react"
import { useMutation } from "@tanstack/react-query"
import { toast } from "sonner"

import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import type { PairingCode } from "./whatsapp-types.ts"

interface PairingFormProps {
  tenant: string
  pairing: PairingCode | null
  onPairing: (pairing: PairingCode) => void
}

/**
 * Menyambungkan tanpa kamera: masukkan nomor, lalu ketik kodenya di
 * WhatsApp. Formulirnya sengaja satu nomor satu aksi.
 */
export function PairingForm({ tenant, pairing, onPairing }: PairingFormProps) {
  const { t } = useTrans()
  const [phone, setPhone] = useState("")

  const request = useMutation({
    mutationFn: () =>
      apiPost<{ data: PairingCode }>(
        `/${tenant}/clinic/broadcasts/connection/pairing`,
        { phone },
      ),
    onSuccess: (result) => onPairing(result.data),
    onError: (err: ApiError) => toast.error(err.message),
  })

  return (
    <>
      {pairing ? (
        <div className="mx-auto max-w-sm rounded-lg border border-border/60 bg-muted/30 px-3 py-2.5 text-center">
          <p className="text-xs text-muted-foreground">
            {t("settings.whatsapp.pairing_code")}
          </p>
          <p className="font-mono text-lg font-semibold tracking-[0.2em] tabular-nums">
            {pairing.pairing_code ?? "—"}
          </p>
          {pairing.expires_at ? (
            <p className="text-xxs text-muted-foreground">
              {t("settings.whatsapp.pairing_expires")}{" "}
              {formatDateTime(pairing.expires_at)}
            </p>
          ) : null}
        </div>
      ) : null}

      <form
        className="mx-auto max-w-sm space-y-2 text-left"
        onSubmit={(event) => {
          event.preventDefault()
          if (phone.trim() === "" || request.isPending) return
          request.mutate()
        }}
      >
        <div className="space-y-1">
          <Label htmlFor="wa-pairing-phone" className="text-xs">
            {t("settings.whatsapp.pairing_phone")}
          </Label>
          <Input
            id="wa-pairing-phone"
            type="tel"
            inputMode="tel"
            autoComplete="tel"
            placeholder="62812xxxxxxx"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            className="tabular-nums"
          />
          <p className="text-xxs text-pretty text-muted-foreground">
            {t("settings.whatsapp.pairing_hint")}
          </p>
        </div>
        <Button
          type="submit"
          variant="outline"
          size="sm"
          className="w-full"
          disabled={phone.trim() === "" || request.isPending}
        >
          {request.isPending
            ? t("general.loading")
            : t("settings.whatsapp.pairing_request")}
        </Button>
      </form>
    </>
  )
}
