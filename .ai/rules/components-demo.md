---
paths:
  - 'resources/js/components/demo/{ModalDialog,SlideOver}.vue'
---

# Components Demo

## iOS-safe modal overlays
On iPhone Safari, modal footers used to flicker or disappear. To keep that from coming back:
- Put blur on the backdrop only from sm: up (`sm:backdrop-blur-sm`).
- Cap the sheet height with `svh`, not `dvh`.
- Lock page scroll by pinning the body (`position: fixed` + `top: -scrollY`, then restore the scroll position on close). `overflow: hidden` alone does not stop iOS touch scrolling.
- Give the footer `env(safe-area-inset-bottom)` padding. This needs `viewport-fit=cover` in app.blade.php.
Every page popup must use these components, not a hand-rolled `fixed inset-0` overlay.
