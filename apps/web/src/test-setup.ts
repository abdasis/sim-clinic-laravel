/**
 * Tambalan lingkungan test.
 *
 * jsdom tidak punya ResizeObserver, sementara primitif Radix yang mengukur
 * dirinya sendiri (Switch, Select, Tooltip) memanggilnya saat mount. Tanpa
 * ini, komponen apa pun yang memuat salah satunya gagal dengan
 * "ResizeObserver is not defined" — galat yang sama sekali tidak berhubungan
 * dengan apa yang sedang diuji.
 */
class ResizeObserverStub implements ResizeObserver {
  observe(): void {}

  unobserve(): void {}

  disconnect(): void {}
}

globalThis.ResizeObserver ??= ResizeObserverStub
