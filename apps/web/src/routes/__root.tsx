import {
  HeadContent,
  Scripts,
  createRootRouteWithContext,
  useRouterState,
} from '@tanstack/react-router'
import { TanStackRouterDevtoolsPanel } from '@tanstack/react-router-devtools'
import { TanStackDevtools } from '@tanstack/react-devtools'
import Footer from '../components/Footer'
import Header from '../components/Header'
import { Toaster } from '#/components/ui/sonner.tsx'
import { TooltipProvider } from '#/components/ui/tooltip.tsx'

import TanStackQueryDevtools from '../integrations/tanstack-query/devtools'

import appCss from '../styles.css?url'

import type { QueryClient } from '@tanstack/react-query'

interface MyRouterContext {
  queryClient: QueryClient
}

/*
 * Berjalan sebelum cat pertama supaya warna dan mode tidak sempat berkedip.
 * Preferensi pengguna yang login menang; pengunjung yang belum masuk tetap
 * memakai kunci 'theme' lama dari pengalih tema publik.
 *
 * Logikanya wajib sama persis dengan applyAppearanceToDom() di
 * hooks/use-appearance.ts — kalau berbeda, akan ada satu kedipan setelah
 * hidrasi saat React menerapkan versinya sendiri.
 */
const THEME_INIT_SCRIPT = `(function(){try{var root=document.documentElement;var accent='teal';var mode=null;try{var raw=window.localStorage.getItem('clinic_user');if(raw){var pref=(JSON.parse(raw)||{}).appearance;if(pref){if(pref.accent)accent=pref.accent;if(pref.mode)mode=pref.mode}}}catch(e){}if(!mode){var stored=window.localStorage.getItem('theme');mode=(stored==='light'||stored==='dark'||stored==='auto')?stored:'auto'}var prefersDark=window.matchMedia('(prefers-color-scheme: dark)').matches;var resolved=mode==='auto'?(prefersDark?'dark':'light'):mode;root.classList.remove('light','dark');root.classList.add(resolved);if(mode==='auto'){root.removeAttribute('data-theme')}else{root.setAttribute('data-theme',mode)}root.setAttribute('data-accent',accent);root.style.colorScheme=resolved;}catch(e){}})();`

export const Route = createRootRouteWithContext<MyRouterContext>()({
  head: () => ({
    meta: [
      {
        charSet: 'utf-8',
      },
      {
        name: 'viewport',
        content: 'width=device-width, initial-scale=1',
      },
      {
        title: 'Klinik Cantik — Perawatan Wajah, Laser, dan Hair Removal',
      },
    ],
    links: [
      {
        rel: 'stylesheet',
        href: appCss,
      },
    ],
  }),
  shellComponent: RootDocument,
})

/** Segmen pertama halaman marketing; semuanya hidup di akar situs. */
const MARKETING_SEGMENTS = new Set([
  'treatments',
  'promo',
  'store',
  'online-booking',
  'locations',
  'artikel',
  'cart',
])

/**
 * Segmen satu-kata yang merupakan halaman aplikasi, bukan slug tenant.
 * Tanpa daftar ini, `/about` dan `/register` ikut kehilangan chrome publik
 * karena bentuk URL-nya sama dengan landing tenant.
 */
const APP_SEGMENTS = new Set([
  ...MARKETING_SEGMENTS,
  'about',
  'register',
  'central',
  'demo',
])

function firstSegment(pathname: string): string {
  return pathname.split('/')[1] ?? ''
}

function RootDocument({ children }: { children: React.ReactNode }) {
  const pathname = useRouterState({ select: (s) => s.location.pathname })
  // Route admin punya chrome sendiri (sidebar-08); skip Header/Footer publik
  // untuk menghindari layout ganda. /central/login tetap publik.
  // ponytail: deteksi via prefix; ganti ke flag route jika admin bertambah kompleks
  const isAdmin =
    /^\/[^/]+\/clinic(\/|$)/.test(pathname) ||
    (/^\/central(\/|$)/.test(pathname) && pathname !== '/central/login')

  // Halaman marketing punya header/footer sendiri (_marketing layout).
  const isMarketing =
    pathname === '/' ||
    MARKETING_SEGMENTS.has(firstSegment(pathname)) ||
    /^\/treatment\//.test(pathname)

  // Company profile tenant (spec 010) juga merender chrome-nya sendiri dari
  // konten yang diatur admin. Satu segmen yang bukan halaman aplikasi
  // dianggap slug tenant.
  const isTenantProfile =
    (/^\/[^/]+\/?$/.test(pathname) &&
      !APP_SEGMENTS.has(firstSegment(pathname))) ||
    /^\/[^/]+\/treatment(\/|$)/.test(pathname)

  // ponytail: halaman ENTRY auth (masuk/daftar/terima undangan), bukan halaman
  // dalam hierarki — panel brand dan tautan kembali ke beranda menggantikan
  // breadcrumb. Tambahkan breadcrumb bila kelak ada halaman auth non-entry.
  const isAuth =
    /^\/(register|central\/login|invitations\/)/.test(pathname) ||
    /^\/[^/]+\/login(\/|$)/.test(pathname)

  const hideChrome = isAdmin || isMarketing || isTenantProfile || isAuth

  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: THEME_INIT_SCRIPT }} />
        <HeadContent />
      </head>
      <body className="font-sans antialiased [overflow-wrap:anywhere] selection:bg-lagoon/24">
        <TooltipProvider>
          {!hideChrome ? <Header /> : null}
          {children}
          {!hideChrome ? <Footer /> : null}
          {/* Tanpa ini setiap toast di aplikasi terkirim ke ruang hampa —
              termasuk pesan gagal simpan yang justru paling perlu dibaca. */}
          <Toaster position="top-right" richColors closeButton />
        </TooltipProvider>
        <TanStackDevtools
          config={{
            position: 'bottom-right',
          }}
          plugins={[
            {
              name: 'Tanstack Router',
              render: <TanStackRouterDevtoolsPanel />,
            },
            TanStackQueryDevtools,
          ]}
        />
        <Scripts />
      </body>
    </html>
  )
}
