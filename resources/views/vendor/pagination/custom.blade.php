@if ($paginator->hasPages())
  <nav class="custom-pagination" role="navigation" aria-label="Pagination Navigation">
    <ul class="pagination-list">
      @if ($paginator->onFirstPage())
        <li class="page-item disabled" aria-disabled="true">
          <span class="page-link"><i class="fa-solid fa-chevron-left"></i> ก่อนหน้า</span>
        </li>
      @else
        <li class="page-item">
          <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="fa-solid fa-chevron-left"></i> ก่อนหน้า</a>
        </li>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
            @else
              <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <li class="page-item">
          <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">ถัดไป <i class="fa-solid fa-chevron-right"></i></a>
        </li>
      @else
        <li class="page-item disabled" aria-disabled="true">
          <span class="page-link">ถัดไป <i class="fa-solid fa-chevron-right"></i></span>
        </li>
      @endif
    </ul>
  </nav>
@endif
