@if ($paginator->hasPages())
    <nav class="pagination" aria-label="เปลี่ยนหน้ารายการ">
        <p>แสดง {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} จาก {{ $paginator->total() }} รายการ</p>
        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true">ก่อนหน้า</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">ก่อนหน้า</a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="หน้า {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">ถัดไป</a>
            @else
                <span aria-disabled="true">ถัดไป</span>
            @endif
        </div>
    </nav>
@endif
