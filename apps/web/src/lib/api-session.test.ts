import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

/**
 * Modul diimpor ulang di tiap tes.
 *
 * `api.ts` menyimpan satu penanda "sedang mengalihkan" supaya 401 beruntun
 * tidak saling menimpa. Penanda itu bertahan antar-tes: sekali menyala, tes
 * berikutnya lolos tanpa pernah benar-benar menguji apa pun. Sempat terjadi
 * di sini, dan baru ketahuan saat kodenya sengaja dirusak.
 */
async function modul() {
  vi.resetModules()

  return import("./api.ts")
}

/**
 * Sesi yang ditolak server harus mengantar pengguna kembali ke halaman masuk.
 *
 * Tanpa itu token yang sudah tidak sah — kedaluwarsa, dicabut, atau hilang
 * karena database server disemai ulang — meninggalkan aplikasi dalam keadaan
 * zombi: halamannya tetap tampil karena nama pengguna dibaca dari
 * localStorage, sementara tiap permintaan ditolak diam-diam. Yang dialami
 * staf klinik: fiturnya rusak satu per satu, tanpa pernah disuruh masuk lagi.
 * "Tambah layanan belum bisa" ternyata itu — HTTP 401 pada katalognya.
 */
const alamat = { pathname: "/mebaclinic/clinic/promos", replace: vi.fn() }

function jawab(status: number, body: unknown = {}) {
  vi.stubGlobal(
    "fetch",
    vi.fn(() =>
      Promise.resolve({
        status,
        ok: status >= 200 && status < 300,
        json: () => Promise.resolve(body),
      } as Response),
    ),
  )
}

beforeEach(() => {
  window.localStorage.clear()
  alamat.replace = vi.fn()
  vi.stubGlobal("location", alamat)
  alamat.pathname = "/mebaclinic/clinic/promos"
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe("loginPathFor", () => {
  it("mengantar ke halaman masuk kliniknya sendiri", async () => {
    const { loginPathFor } = await modul()

    expect(loginPathFor("/mebaclinic/clinic/promos")).toBe("/mebaclinic/login")
    expect(loginPathFor("/demo/clinic/reports")).toBe("/demo/login")
  })

  it("mengantar admin platform ke halaman masuk pusat", async () => {
    const { loginPathFor } = await modul()

    expect(loginPathFor("/central/tenants")).toBe("/central/login")
  })

  /** Menebak nama klinik dari alamat asing hanya melahirkan 404 kedua. */
  it("mengembalikan ke beranda saat alamatnya tidak punya klinik", async () => {
    const { loginPathFor } = await modul()

    expect(loginPathFor("/")).toBe("/")
    expect(loginPathFor("")).toBe("/")
  })
})

describe("sesi yang ditolak server", () => {
  it("membersihkan token dan mengantar kembali ke halaman masuk", async () => {
    const { apiGet, setToken } = await modul()
    setToken("token-basi")
    window.localStorage.setItem("clinic_user", '{"name":"Admin"}')
    jawab(401, { message: "Unauthenticated." })

    await expect(apiGet("/mebaclinic/clinic/services")).rejects.toMatchObject({
      status: 401,
    })

    expect(window.localStorage.getItem("clinic_token")).toBeNull()
    expect(window.localStorage.getItem("clinic_user")).toBeNull()
    expect(alamat.replace).toHaveBeenCalledWith("/mebaclinic/login?expired=1")
  })

  /**
   * Salah kata sandi juga menjawab 401. Mengalihkannya ke halaman masuk yang
   * sedang dibuka hanya memuat ulang formulirnya tepat saat pesan galatnya
   * hendak dibaca.
   */
  it("tidak mengalihkan saat yang gagal justru upaya masuk", async () => {
    // Token basi masih tersimpan: itu justru keadaan yang paling mungkin —
    // sesinya mati, pengguna sampai di halaman masuk, lalu salah mengetik
    // kata sandi. Tanpa pengecualian ini, pesan galatnya hilang dimuat ulang.
    const { apiPost, setToken } = await modul()
    setToken("token-basi")
    alamat.pathname = "/mebaclinic/login"
    jawab(401, { message: "Email atau kata sandi salah." })

    await expect(
      apiPost("/mebaclinic/login", { email: "a@b.c", password: "salah" }),
    ).rejects.toMatchObject({ status: 401 })

    expect(alamat.replace).not.toHaveBeenCalled()
  })

  /** Tanpa token, 401 bukan sesi yang berakhir — tidak ada yang perlu dibuang. */
  it("diam saja saat memang belum pernah masuk", async () => {
    const { apiGet } = await modul()
    jawab(401, { message: "Unauthenticated." })

    await expect(apiGet("/mebaclinic/clinic/services")).rejects.toMatchObject({
      status: 401,
    })

    expect(alamat.replace).not.toHaveBeenCalled()
  })

  /** Penolakan izin bukan sesi yang berakhir; membuangnya keluar itu kasar. */
  it("tidak mengusir keluar saat galatnya soal izin", async () => {
    const { apiGet, setToken } = await modul()
    setToken("token-sah")
    jawab(403, { message: "Anda tidak punya akses." })

    await expect(apiGet("/mebaclinic/clinic/services")).rejects.toMatchObject({
      status: 403,
    })

    expect(window.localStorage.getItem("clinic_token")).toBe("token-sah")
    expect(alamat.replace).not.toHaveBeenCalled()
  })

  it("membiarkan permintaan yang berhasil apa adanya", async () => {
    const { apiGet, setToken } = await modul()
    setToken("token-sah")
    jawab(200, { data: [{ id: 1 }] })

    await expect(apiGet("/mebaclinic/clinic/services")).resolves.toEqual({
      data: [{ id: 1 }],
    })

    expect(alamat.replace).not.toHaveBeenCalled()
  })
})
