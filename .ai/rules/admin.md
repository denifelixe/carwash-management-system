---
paths:
  - '{app/Http/Controllers/Admin/**,app/Http/Controllers/Demo/**,resources/js/pages/admin/**}'
---

# Admin

## Live and demo admin modules share one Vue page
When a demo module gets a live counterpart, move its page from resources/js/pages/demo/admin/X.vue to resources/js/pages/admin/X.vue and render 'admin/X' from BOTH App\Http\Controllers\Admin\XController (live, real DB) and App\Http\Controllers\Demo\XController (fixtures). There is no second copy of the page.

The page branches on a `mode: 'demo' | 'live'` prop: demo mutates the in-memory useCarwashWorkflow store, live posts via useForm to the live controller and re-reads the reloaded props. Both controllers must send the same prop set, including `capabilities`.

Live controllers get shell props from AdminShell::props($admin, $title, $moduleKey); add the module's route to AdminShell::moduleEntry()'s match and its key to the Gate loop in AppServiceProvider so admin.<module>.<create|read|update|delete> exist.

Shared order/service/member payload shapes live in App\Support\Admin\OrderPresenter; shared reads in App\Support\Admin\OrderQueries. Do not re-hand-roll them per controller.

Many demo tests assert exact source strings from these Vue files — after editing a page, run the whole suite, not just the module's test.</note>
</invoke>
