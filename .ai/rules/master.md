---
paths:
  - 'resources/js/pages/admin/master/**'
---

# Master

## Drag reordering uses pointer events, not native HTML5 drag
Master Layanan reorders rows with pointer events (pointerdown on the row, then pointermove/pointerup listeners on the document) plus a hand-rolled requestAnimationFrame auto-scroll near the viewport edges.

Native HTML5 drag was tried first and rejected: it swallows the mouse wheel while a row is held and its auto-scroll targets the nearest overflow wrapper instead of the page. Row rects are re-measured every frame so wheel and auto scrolling both stay honest.

Two traps, both of which silently dropped the drag:
- Do not use setPointerCapture. Capture is released as soon as the row is re-inserted at a new index, and the lostpointercapture that follows ended every upward drag.
- Do not disable controls inside a draggable row. A disabled button swallows pointer events, so the press never reaches the row and the browser starts its own native drag, painting a "no drop" cursor and firing pointercancel. Use pointer-events-none plus a guard in the click handler, and keep draggable="false" with @dragstart.prevent on the row.

Reordering only runs on the full list — entering sort mode clears the search and category chips, because dragging a filtered subset would move rows the operator cannot see. The new order is local until the floating save bar is submitted; Batal restores the snapshot.
