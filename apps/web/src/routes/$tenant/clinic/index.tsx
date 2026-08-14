import { createFileRoute } from '@tanstack/react-router'
// Halaman index clinic sengaja kosong; layout route.tsx mengalihkan ke modul pertama yang diizinkan per peran.
export const Route = createFileRoute("/$tenant/clinic/")({
  component: () => null,
})