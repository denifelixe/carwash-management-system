---
paths:
  - '{app/Support/Admin/OrderLogCsv.php,app/Http/Controllers/Admin/ReportController.php,app/Http/Controllers/Demo/ReportController.php}'
---

# Controllers Demo

## The report's Excel download is a streamed CSV, written for Excel's Indonesian defaults
There is no spreadsheet library in this project and none was approved, so the download is a CSV built with response()->streamDownload(). Do not add maatwebsite/excel or phpspreadsheet to "improve" it without asking.

Three things in OrderLogCsv exist because of how Excel reads a file, not because of taste — do not simplify them away:
- the ';' delimiter, because ',' is the decimal separator on an Indonesian install and a comma-delimited file opens there as a single column;
- the leading UTF-8 BOM, without which every em dash and accented name arrives mangled;
- phones written in dashed groups, because a bare run of digits is read as a number and silently loses its leading zero. Never "fix" this with a zero-width space or an ="..." formula: the first pollutes copy-paste, the second is a CSV injection vector on a user-entered field.

Rows arrive as a LazyCollection (ReportQueries::orderLogRows) and are written as they stream — the download covers the whole range, not the page on screen, and a year of orders must never be materialised to build it.

The page reaches it with a plain <a href download>, never router.get: an Inertia visit would try to parse the file as a page. Both consoles have the route, so the shared page branches on `mode` for the URL.

fputcsv quotes any field containing a space, so tests assert `;"Sealant Body";` and not `;Sealant Body;`. That is correct CSV; Excel unwraps it.</note>
