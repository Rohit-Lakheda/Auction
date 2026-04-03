@if(method_exists($rows, 'total') && $rows->total() > 0)
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:14px;">
        <div style="color:#6c757d;font-size:14px;">
            Showing {{ $rows->firstItem() }}-{{ $rows->lastItem() }} of {{ $rows->total() }} records
            | Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}
        </div>
        <div>{{ $rows->links() }}</div>
    </div>
@endif
