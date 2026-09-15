---
paths:
  - '{app/Models/StockItem.php,app/Actions/Admin/RecordStockMovement.php,app/Actions/Admin/SaveStockItem.php,app/Support/Admin/StockQueries.php,app/Http/Requests/Admin/StoreStockMovementRequest.php}'
---

# Http Requests Admin

## On hand is written only by RecordStockMovement, under a row lock
stock_items.quantity is denormalised so the list does not sum the movement log per row. RecordStockMovement is its only writer, and it locks the item with lockForUpdate() before reading the starting figure — two cashiers recording in the same second must not both read the same number.

SaveStockItem never touches quantity on an edit. On create it takes an opening stock and files it as a real `masuk` movement noted "Stok awal", so every unit on hand is explained by a row in the log. quantity is absent from UpdateStockItemRequest for the same reason; a form that posts it is ignored.

Stock may not go negative. StoreStockMovementRequest::after() measures the movement against the item and rejects an out larger than on hand, or a correction that would cross zero, naming what is actually left. The demo page clamps to zero instead — do not copy that into the live path; a till has to tell the operator the truth.

Ins and outs are posted as positive amounts and get their sign from the type; `penyesuaian` arrives already signed and may not be 0. RecordStockMovement::delta() is the one place that mapping lives, and the form request reuses it so validation and the write can never disagree.

Items are deactivated, never deleted (manager and cashier both have can_delete = false on this module). A deactivated item drops out of StockQueries::itemOptions() and is refused new movements, but keeps its history.
