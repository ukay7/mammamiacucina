<div class="d-flex justify-content-between align-items-center flex-wrap mt-3" style="gap:12px">
<p class="mb-0" role="status">Showing {{ number_format($records->firstItem()??0) }}–{{ number_format($records->lastItem()??0) }} of {{ number_format($records->total()) }} records</p>
{{ $records->links() }}
</div>
