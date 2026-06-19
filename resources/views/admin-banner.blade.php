@if ($message = ($banner ?? null))
    <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        {{ $message }}
    </div>
@endif
